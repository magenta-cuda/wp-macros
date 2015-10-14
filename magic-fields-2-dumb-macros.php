<?php

/*
 * Description:   A Tiny Post Content Template Interpreter and a Shortcode Tester
 * Documentation: http://tpcti.wordpress.com/
 * Author:        Magenta Cuda
 * License:       GPL2
 */

/*  Copyright 2013  Magenta Cuda

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace {
    
    # A comparison expression is of the form $#alpha#='gamma', $#alpha#="gamma", $#alpha#=$#beta# or $#alpha# (last case is not really a comparison)
    # the regex for a comparison expression is really ugly because wptexturize() runs before do_shortcode() and unfortunately texturizes the quote marks
    # of shortcode parameters - i.e. changes straight quote marks into curly quote marks
    define( 'REGEX_COMP_EXPR',
        '(\s*\$#([\w-]+)#(\s*(=|!=|<|<=|>=|>)\s*((\$#([\w-]+)#)|(("|\'|&#8216;|&#8217;|&#8220;|&#8221;|&#8242;|&#8243;)(.*?)("|\'|&#8216;|&#8217;|&#8220;|&#8221;|&#8242;|&#8243;))))?\s*)'
    );
    
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
    
    # in the following the term "content template" and the term "content macro" refer to the same thing
    # "content macro" was the original term for "content template"

    # the if ( $TPCTI_MF2_ACTIVE ) { } else { } should be written in the following style so that the blocks to be
    # excluded can be easily removed by a source code preprocessor; do not indent the included/excluded blocks

    #   if ( $TPCTI_MF2_ACTIVE ) {
    #   ...
    #   } else {   # if ( $TPCTI_MF2_ACTIVE ) {
    #   ...
    #   }   # if ( $TPCTI_MF2_ACTIVE ) {

    $TPCTI_MF2_ACTIVE = is_plugin_active( 'magic-fields-2-toolkit/magic-fields-2-toolkit-loader.php' );

    # $mf2tk_the_do_macro will hold the function that implements the [show_macro] shortcode and is the only exposed global
    # this needs to exists only if you need to invoke the [show_macro] shortcode function directly from your own code
    # $construct is not global since this file is included from inside a function

    global $mf2tk_the_do_macro;

    $construct = function( ) use ( $TPCTI_MF2_ACTIVE, &$mf2tk_the_do_macro ) {

        # $do_macro will hold the function that implements the [show_macro] shortcode
        $do_macro = NULL;

        if ( $TPCTI_MF2_ACTIVE ) {
        $options = get_option( 'tpcti_options', ( object ) [
            'shortcode_name'          => 'show_macro',
            'shortcode_name_alias'    => 'mt_template',
            'content_macro_post_type' => 'content_macro',
            'filter'                  => '@',
            'separator'               => ', ',
            'post_member'             => '.',
            'use_native_mode'         => false
        ] );
        } else {   # if ( $TPCTI_MF2_ACTIVE ) {
        $options = get_option( 'tpcti_options', ( object ) [
            'shortcode_name'          => 'mt_template',
            'shortcode_name_alias'    => '',
            'content_macro_post_type' => 'mt_content_template',
            'filter'                  => '@',
            'separator'               => ', ',
            'post_member'             => '.',
            'disable_wptexturize'     => false
        ] );
        }   # if ( $TPCTI_MF2_ACTIVE ) {
        $error = NULL;

        add_action( 'init', function() use ( $options ) {
            global $wpdb;

            # the content macros are stored in its own post type

            register_post_type( $options->content_macro_post_type, [
                'label'               => 'Post Content Templates',
                'description'         => 'defines a post content template for HTML and WordPress shortcodes',
                'public'              => TRUE,
                'exclude_from_search' => TRUE,
                'public_queryable'    => FALSE,
                'show_ui'             => TRUE,
                'show_in_nav_menus'   => FALSE,
                'show_in_menu'        => TRUE,
                'menu_position'       => 50
            ] );

            # insert a sample content macro 

            if ( !$wpdb->get_var( $wpdb->prepare( <<<EOD
SELECT COUNT(*) FROM $wpdb->posts
    WHERE post_type = %s AND post_title = 'A Post Content Template Example' AND post_status = 'publish'
EOD
                , $options->content_macro_post_type ) ) ) {
                wp_insert_post( [
                    'post_type'    => $options->content_macro_post_type,
                    'post_name'    => 'a-post-content-template-example',
                    'post_title'   => 'A Post Content Template Example',
                    'post_status'  => 'publish',
                    'post_content' => <<<'EOD'
<!-- This is a useless but tutorial post content template that works in any post since it does not use any custom fields. -->
[mt_template it="alpha:'1';'2'"]
<!-- $#beta# = "2"; -->
#if( $#alpha# = "1" )#
<span>Hello</span>
#else#
#if( $#alpha# = $#beta# )#
<span> World</span>
#endif#
#endif#
[/mt_template]
EOD
                ] );
			}   # if ( !$wpdb->get_var( <<<EOD
  
        } );

        if ( is_admin( ) ) {
            
            # AJAX action 'mf2tk_update_content_macro' handles "save as template" action from post content editor
            
            add_action( 'wp_ajax_mf2tk_update_content_macro', function() use ( $options ) {
                global $wpdb;
                $ids = $wpdb->get_col( $wpdb->prepare( <<<EOD
SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_title = %s AND post_status = 'publish'
EOD
                , $options->content_macro_post_type, $_POST[ 'title' ] ) );
                $post = [
                    'post_type'    => $options->content_macro_post_type,
                    'post_name'    => $_POST['slug'],
                    'post_title'   => $_POST['title'],
                    'post_status'  => 'publish',
                    'post_content' => $_POST['text']
                ];
                if ( $ids ) {
                    $post['ID'] = $ids[0];
                    $id0 = wp_update_post( $post );
                } else {
                    $id1 = wp_insert_post( $post );
                }
                die( !empty ( $id0 ) ? "Content template $id0 updated."
                    : ( !empty( $id1 ) ? "Content template $id1 created."
                        : "Error: Content template not created/updated." ) );
            } ); # add_action( 'wp_ajax_mf2tk_update_content_macro', function() {

            # AJAX action 'wp_ajax_tpcti_eval_post_content'
            # handles evaluate HTML fragments from post content editor shortcode tester
            
            add_action( 'wp_ajax_tpcti_eval_post_content', function( ) use ( &$do_macro, $TPCTI_MF2_ACTIVE ) {
                if ( $TPCTI_MF2_ACTIVE ) {
                require_once( MF_PATH . '/mf_front_end.php' );   # MF2 only
                }   # if ( $TPCTI_MF2_ACTIVE ) {
                echo $do_macro( [ 'post' => $_POST[ 'post_id' ] ], stripslashes( $_POST[ 'post_content' ] ) );
                exit;
            } );   # add_action( 'wp_ajax_tpcti_eval_post_content', function( ) use ( &$do_macro ) {
         
            # content macros should not be viewable

            add_filter( 'post_row_actions', function( $actions, $post ) use ( $options ) {
                if ( get_post_type( $post ) === $options->content_macro_post_type ) {
                    unset( $actions['view'] );
                }
                return $actions;
            }, 10, 2 );

            # things to do only on post.php and post-new.php admin pages

            $post_editor_actions = function( ) use ( $options, $TPCTI_MF2_ACTIVE ) {

                # insert Content Template database into head of document
                # later JavaScript code will read this database and build the options for the select HTML element
                # of "Insert Template" popup

                add_action( 'admin_head', function( ) use ( $options, $TPCTI_MF2_ACTIVE ) {
                    global $wpdb;
                    $additional_select_clause_for_content_macros = " AND post_name NOT LIKE 'search-result-template-for-%%'";   # MF2 only
                    $results = $wpdb->get_results( $wpdb->prepare( <<<EOD
SELECT post_name, post_title, post_content FROM $wpdb->posts
    WHERE post_type = %s AND post_status = 'publish'$additional_select_clause_for_content_macros ORDER BY post_title
EOD
                    , $options->content_macro_post_type ), OBJECT );
                    if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
                        $name = 'macro';
                    } else {   # if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
                        $name = 'name';
                    }   # } else {   # if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
?>
<script type="text/javascript">
var mf2tk_globals=mf2tk_globals||{};
mf2tk_globals.mf2tk_alt_template={
    shortcode:"<?php echo $options->shortcode_name; ?>",
    shortcode_alias:"<?php echo $options->shortcode_name_alias; ?>",
    name:"<?php echo $name; ?>",
    templates:{}
};
(function(){
    var templates=mf2tk_globals.mf2tk_alt_template.templates;
<?php
    # generate javascript assignment statements: templates[ name ] = { content: content, title: title };
    echo implode( "\n\n", array_map( function( $result ) {
        return "templates['$result->post_name']={content:'" . str_replace( "\n", "\\n\\\n",
            str_replace( "\r", '', htmlentities( $result->post_content, ENT_QUOTES, 'UTF-8' ) ) )
            . "',title:'" . $result->post_title . "'};";
    }, $results ) ) . "\n\n";
?>
}());
</script> 
<?php   
                } );

                add_action( 'admin_enqueue_scripts', function( $hook ) {
                    if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
                        return;
                    }
                    wp_enqueue_style(  'mf2tk_macros_admin', plugins_url( 'css/mf2tk_macros_admin.css', __FILE__ ) );
                    wp_enqueue_script( 'mf2tk_macros_admin', plugins_url(  'js/mf2tk_macros_admin.js',  __FILE__ ), [ 'jquery' ] );
                } );

                # $insert_template() outputs the HTML for the "Insert Template" popup

                $insert_template = function( ) {
?>
<!-- start insert_template -->
<div id="mf2tk-popup-outer" style="display:none;">
</div>
<div id="mf2tk-alt-template" class="mf2tk-popup" style="display:none;">
    <h3>Insert Post Content Template</h3>
    <button id="button-mf2tk-alt-template-close">X</button>
    <div style="clear:both;">
        <div class="mf2tk-field-input-optional">
            <div class="mf2tk-field_value_pane" style="clear:both;">
                Select Template:
                <select id="mf2tk-alt_template-select"></select><br>
                <input id="mf2tk-alt_template-post_name" type="text" class="mf2tk-how-to-use value="" readonly><br>
- To display this post content template <button class="mf2tk-how-to-use">select,</button> copy and paste this into the editor
in <strong>Text</strong> mode.<br>
            </div>
        </div>
        <div class="mf2tk-field-input-optional">
            <button class="mf2tk-field_value_pane_button">Open</button>
            <h6>Post Content Template Definition</h6>
            <div class="mf2tk-field_value_pane" style="display:none;clear:both;">
                <textarea id='mf2tk-alt_template-post_content' rows='8' readonly></textarea>
            </div>
        </div>
    </div>
</div>
<!-- end insert_template -->
<?php
                };   # $insert_template = function( ) {

                # $shortcode_tester() outputs the HTML for the "Shortcode Tester" popup

                $shortcode_tester = function( ) use ( $options ) {
?>
<!-- start shortcode tester -->
<div id="mf2tk-shortcode-tester" class="mf2tk-popup" style="display:none;">
    <h3>Shortcode Tester</h3>
    <button id="button-mf2tk-shortcode-tester-close">X</button>
    <div style="padding:0;margin:0;clear:both;">
        <div style="padding:0px 20px;margin:0;">
            Enter post content template statements, HTML, [<?php echo $options->shortcode_name; ?>]
            or other WordPress shortcodes in the Source text area.<br />
            Click the Evaluate button to display the generated HTML from WordPress shortcode processing
            in the Result text area.<br />
            <button id="mf2tk-shortcode-tester-evaluate" class="mf2tk-shortcode-tester-button">Evaluate</button>
            <button id="mf2tk-shortcode-tester-show-source" class="mf2tk-shortcode-tester-button">Show Source Only</button>
            <button id="mf2tk-shortcode-tester-show-result" class="mf2tk-shortcode-tester-button">Show Result Only</button>
            <button id="mf2tk-shortcode-tester-show-both" class="mf2tk-shortcode-tester-button">Show Both</button>
        </div>
        <div class="mf2tk-shortcode-tester-half">
            <div id="mf2tk-shortcode-tester-area-source" class="mf2tk-shortcode-tester-area">
                <h3>Source</h3>
                <textarea rows="12"></textarea>
            </div>
        </div>
        <div class="mf2tk-shortcode-tester-half">
            <div  id="mf2tk-shortcode-tester-area-result" class="mf2tk-shortcode-tester-area">
                <h3>Result</h3>
                <textarea rows="12" readonly></textarea>
            </div>
        </div>
    </div>
</div>
<!-- end shortcode tester -->
<?php
                };   # $shortcode_tester = function( ) {

                # the "Insert Template" and "Shortcode Tester" are only injected on post.php and post-new.php admin pages
                add_action( 'admin_footer-post.php',     $insert_template  );
                add_action( 'admin_footer-post-new.php', $insert_template  );
                add_action( 'admin_footer-post.php',     $shortcode_tester );
                add_action( 'admin_footer-post-new.php', $shortcode_tester );
                
            };
            add_action( 'load-post-new.php', $post_editor_actions );
            add_action( 'load-post.php',     $post_editor_actions );

        } else {   # if ( is_admin( ) ) {

            # wptexturize() sometimes texturizes the quote marks in shortcode attributes which breaks my parser so
            
            if ( $options->disable_wptexturize ) {
                if ( !remove_filter( 'the_content', 'wptexturize' ) ) {
                    error_log( 'Plugin: A Tiny Post Content Template Interpreter - failed to remove filter wptexturize' );
                }
            }
            
            # AJAX action 'tpcti_eval_post_content' allows access to post content shortcode evaluator from the frontend client
            
            add_action( 'wp_ajax_nopriv_tpcti_eval_post_content', function( ) use ( &$do_macro ) {
                require_once( MF_PATH . '/mf_front_end.php' );   # MF2 only
                echo $do_macro( [ 'post' => $_POST[ 'post_id' ] ], stripslashes( $_POST[ 'post_content' ] ) );
                exit;
            } );   # add_action( 'wp_ajax_nopriv_tpcti_eval_post_content', function( ) use ( &$do_macro ) {

        }
        
        # $find_embedded_macros() finds the locations of directly (outer only) embedded macros
        # $ranges will be returned as an array of [ begin, end + 1 ] entries and should be passed by reference
        # the return value is the location of the end of the current macro + 1 or a string error message
        # the macro body should always have a "[/show_macro]" terminator  

        $find_embedded_macros = NULL;
        $find_embedded_macros = function( $macro, &$ranges ) use ( &$find_embedded_macros, $options ) {
            # TODO: shortcode can have aliases
            $ranges = [ ];
            $i = 0;
            while ( TRUE ) {
                $j = strpos( substr( $macro, $i ), "[{$options->shortcode_name}" );
                $k = strpos( substr( $macro, $i ), "[/{$options->shortcode_name}" );
                $current_shortcode = $options->shortcode_name;
                if ( $options->shortcode_name_alias ) {
                    # the alias may also have been used so check for it and use it if it occurs earlier
                    $j1 = strpos( substr( $macro, $i ), "[{$options->shortcode_name_alias}" );
                    if ( $j1 !== FALSE && ( $j === FALSE || $j1 < $j ) ) {
                        $j = $j1;
                        $current_shortcode = $options->shortcode_name_alias;
                    }
                    $k1 = strpos( substr( $macro, $i ), "[/{$options->shortcode_name_alias}" );
                    if ( $k1 !== FALSE && ( $k === FALSE || $k1 < $k ) ) {
                        $k = $k1;
                    }
                }
                $current_shortcode_len = strlen( $current_shortcode );
                if ( $k !== FALSE && ( $j === FALSE || $k < $j ) ) {
                    # this '[/show_macro]' terminates the current macro so
                    # this should be the exit for all properly constructed macros,
                    # i.e., done with a matching start '[show_macro]' and end '[/show_macro]' shortcodes 
                    return $i + $k;
                }
                if ( $j !== FALSE && ( $k !== FALSE && $j < $k ) ) {
                    # find closing ']' - N.B. no valid macro parameter value should have a an embedded ']'
                    $j = strpos( substr( $macro, $i + $j + $current_shortcode_len + 1 ), ']' )
                        + $i + $j + $current_shortcode_len + 2;
                    # found start of inner [show_macro]; ranges of inner macros are not returned
                    $inner_ranges;
                    $m = $find_embedded_macros( substr( $macro, $j ), $inner_ranges );
                    if ( is_string( $m ) ) {
                        # error detected so just return error message
                        return $m;
                    }
                    $ranges[] = [ $j, $j + $m ];
                    $i = $j + $m + strlen( $current_shortcode ) + 3;   # really 3 or 4 but it doesn't really matter
                    continue;
                }
                # exit here if no matching '[/show_macro]' was found
                # this should not happen with matching start '[show_macro]' and end '[/show_macro]' shortcodes
                # nested shortcodes must be of the form "[show_macro]...[show_macro1]...[/show_macro1]...[/show_macro]"
                # since WordPress cannot correctly parse nested identical shortcodes
                return <<<EOD
<div style="border:2px solid red;color:red;padding:5px;">{$options->shortcode_name} error:
missing "[/{$options->shortcode_name}]". N.B., nested templates must use the form " ... [{$options->shortcode_name}]
... [{$options->shortcode_name}1] ... [/{$options->shortcode_name}1] ... [/{$options->shortcode_name}] ... "
since WordPress cannot correctly parse nested identical shortcodes.</div>
EOD;
            }
        };

        # $get_custom_field() returns the value(s) of the custom field $field passed through any specified filters.
        # You can modify or replace $get_custom_field() to suit your particular custom fields.
        # $get_custom_field() should implement the psuedo filters: 'field_name', 'count', 'indexes'
        # and the array dereference filter '[n]' where n is a non-negative integer, e.g., [7]
       
        if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
        $get_custom_field = function( $field, $as_array = FALSE ) use ( $options ) {
            # check if there is an @filter suffix
            $filter = '';
            if ( $at = strpos( $field, $options->filter ) ) {
                $filter = substr( $field, $at + 1 );
                $field = substr( $field, 0, $at );
            }
            $show_custom_field_tag = mf2tk\get_tags( )[ 'show_custom_field' ];
            if ( $as_array ) {
                # handle the value of multi-valued fields as separate distinct multiple values
                return explode( '!@#$%', do_shortcode( <<<EOD
[$show_custom_field_tag field="$field" filter="$filter" separator="!@#$%"]
EOD
                ) );
            } else {
                return do_shortcode( <<<EOD
[$show_custom_field_tag field="$field" filter="$filter" separator="{$options->separator}"]
EOD
                );
            }
        };
        } else {   # if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
        $get_custom_field = function( $field_specifier, $as_array = FALSE ) use ( $options, &$error ) {
            global $post;
            $post_id = $post->ID;
            # a field specifier looks like this: alpha.beta@<-1>@sprintf("%3.3s",$) where . indicates a post member operation
            # break the field specifier on the post member operator
            preg_match_all( '#(\w+(@((<-?\d+>)|(\w+(\((((("|\').*?("|\'))|\d+(\.\d*)?|\$),)*((("|\').*?("|\'))|\d+(\.\d*)?|\$)\))?)))*)(\\'
                . $options->post_member . '|$)#', $field_specifier, $fields );
            $fields = $fields[ 1 ];
            $last = count( $fields ) - 1;
            foreach ( $fields as $i => $field ) {
                # check if there is an @filter suffix
                $filter = '';
                if ( $at = strpos( $field, $options->filter ) ) {
                    $filter = substr( $field, $at + 1 );
                    $field = substr( $field, 0, $at );
                }
                if ( $filter === 'field_name' ) {
                    return $as_array ? [ $field ] : $field;
                }
                $value = [ ];
                $meta = get_post_meta( $post_id, $field );
                if ( !$meta ) {
                    $error = <<<EOD
<div style="border:2px solid red;padding:5px;">Error: custom field "$field" does not exists.</div>
EOD;
                    return $as_array ? [ ] : '';
                }
                foreach ( $meta as $v ) {
                    # handle custom field values that are arrays. get_post_meta() can return
                    # 1) an array of scalar values,
                    # 2) a single element array whose only element is an array of scalars
                    # 3) anything else
                    # this really handles only 1) and 2) well. 3) returns a merged array with ambiguous indexing.
                    $failed = FALSE;
                    if ( is_scalar( $v ) ) {
                        $value[ ] = $v;
                    } else if ( is_array( $v ) ) {
                        if ( array_reduce( $v, function( $c, $i ) {
                            return $c && is_scalar( $i );
                        }, TRUE ) ) {
                            $value = array_merge( $value, $v );
                        } else {
                            $failed = TRUE;
                        }
                    } else {
                        $failed = TRUE;
                    }
                    if ( $failed ) {
                        $error = <<<EOD
<div style="border:2px solid red;padding:5px;">Error: The value of the custom field "$field" is not a scalar or an array of scalars.</div>
EOD;
                        return $as_array ? [ ] : '';
                    }
                }
                # check if the filter is a request for count of values
                if ( $filter === 'count' ) {
                    return $as_array ? [ count( $value ) ] : count( $value );
                }
                # check if the filter is a request for indexes
                if ( $filter === 'indexes' || $filter === 'indices' ) {
                    $count = count( $value );
                    $value = [ ];
                    for ( $i = 0; $i < $count; $i++ ) {
                        $value[ ] = (string) $i;
                    }
                    return $as_array ? $value : implode( ',', $value );
                }
                if ( $filter ) {
                    # the filter may be an '@' separated sequence of filter function names
                    $filters = explode ( $options->filter, $filter );
                    # resolve index aliases
                    if ( $filters[ 0 ] === 'first' ) {
                        $filters[ 0 ] = '<0>';
                    } else if ( $filters[ 0 ] === 'last' ) {
                        $filters[ 0 ] = '<-1>';
                    }
                    # check if the first filter is an array dereference
                    if ( preg_match( '/^<(-?)(\d+)>$/', $filters[ 0 ], $matches ) === 1 ) {
                        $j = $matches[ 2 ];
                        if ( $matches[ 1 ] === '-' ) {
                            # a negative index is relative to the end of the array, e.g. -1 references the last element
                            $j = count( $value ) - $j;
                        }
                        if ( !array_key_exists( $j, $value ) ) {
                            $error = <<<EOD
<div style="border:2px solid red;padding:5px;">Error: {$matches[1]}{$matches[2]} is an invalid index for custom field "$field".</div>
EOD;
                            return $as_array ? [ ] : '';
                        }
                        $value = [ $value[ $j ] ];
                        unset( $filters[ 0 ] );
                    }                    
                    $value = array_map( function( $v ) use ( $field, $filters, $options, &$error ) {
                        foreach ( $filters as $f ) {
                            if ( preg_match( '#^\w+$#', $f ) ) {
                                # filter function name only specified
                                $a = [ $v ];
                            } else if ( preg_match( '#^(\w+)\(((\s*((("|\').*?\6)|\d+|\$),)*\s*((("|\').*?\9)|\d+|\$))\s*\)$#', $f, $m ) ) {
                                # filter function specifier has optional arguments
                                $f = $m[ 1 ];
                                # the optional arguments must be either single or double quoted strings or integers
                                # the position of the main argument to the filter must be specified by a $
                                # e.g. alpha@sprintf('The value is: %s',$)
                                # e.g. beta@number_format($,2,'.',',')
                                if ( preg_match_all( '#((("|\').*?\3)|\d+|\$)(,|$)#', $m[ 2 ], $a, PREG_PATTERN_ORDER ) ) {
                                    $a = $a[ 1 ];
                                    array_walk( $a, function( &$v1 ) use ( $v ) {
                                        if ( $v1 === '$' ) {
                                            # main filter argument
                                            $v1 = $v;
                                        } else if ( substr_compare( $v1, '"', 0, 1 ) === 0 || substr_compare( $v1, '\'', 0, 1 ) === 0 ) {
                                            # quoted string
                                            $v1 = substr( $v1, 1, -1 );
                                        } else {
                                            # integer
                                            $v1 = intval( $v1 );
                                        }
                                    } );
                                }
                            } else {
                                $error = <<<EOD
<div style="border:2px solid red;padding:5px;">Error: $f is an invalid filter for custom field "$field".</div>
EOD;
                                return $as_array ? [ ] : '';
                            }
                            if ( function_exists( $f ) ) {
                                $v = call_user_func_array( $f, $a );
                            } else if ( !preg_match( '/^<(\d+)>$/', $f ) ) {
                                $error = <<<EOD
<div style="border:2px solid red;padding:5px;">Error: $f is an invalid filter for custom field "$field".</div>
EOD;
                                return '';
                            }
                        }
                        return $v;
                    }, $value );
                    if ( $error ) {
                        return $as_array ? [ ] : '';
                    }
                    # check if the last filter is an array dereference
                    # for efficiency an array dereference should be done as the first filter but the following is done for compatibility with earlier versions
                    if ( preg_match( '/^<(\d+)>$/', $filters[ count( $filter ) - 1 ], $matches ) === 1 ) {
                        if ( !array_key_exists( $matches[ 1 ], $value ) ) {
                            $error = <<<EOD
<div style="border:2px solid red;padding:5px;">Error: $matches[1] is an invalid index for custom field "$field".</div>
EOD;
                            return $as_array ? [ ] : '';
                        }
                        $value = [ $value[ $matches[1] ] ];
                    }
                }
                # everything but the last field should be post id
                if ( $i !== $last ) {
                    if ( count( $value ) !== 1 ) {
                        $error = <<<EOD
<div style="border:2px solid red;padding:5px;">Error: The value of custom field "$field" is not a single value.</div>
EOD;
                        return $as_array ? [ ] : '';
                    }
                    $post_id = ( integer ) $value[0];
                    if ( !$post_id ) {
                        $error = <<<EOD
<div style="border:2px solid red;padding:5px;">Error: The value of custom field "$field" is not a post id.</div>
EOD;
                        return $as_array ? [ ] : '';
                    }
                    continue;
                }
                if ( $as_array ) {
                    return $value;
                }
                return implode( $options->separator, $value );
            }
        };   # $get_custom_field = function( $field_specifier, $as_array = FALSE ) use ( $options, &$error ) {
        }   # } else {   # if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
        # $do_macro implements the [show_macro] shortcode

        $mf2tk_the_do_macro = $do_macro = function( $atts, $macro )
            use ( &$do_macro, $find_embedded_macros, $get_custom_field, $options, &$error, $TPCTI_MF2_ACTIVE ) {
            global $post;
            global $wpdb;
            
            # $eval_comp() compares $value with $right using the comparison operator $op and returns TRUE or FALSE
            
            $eval_comp = function( $value, $op, $right ) {
                error_log( '$eval_comp():$value=' . $value );
                error_log( '$eval_comp():$op=' . $op );
                error_log( '$eval_comp():$right=' . $right );
                switch( $op ) {
                case '=':
                    return $value === $right;
                case '!=':
                    return $value !== $right;
                case '<':
                    return $value < $right;
                case '<=':
                    return $value <= $right;
                case '>=':
                    return $value >= $right;
                case '>':
                    return $value > $right;
                }
            };   # $eval_comp = function( $value, $op, $right ) {
            
            # $eval_expr() parses and evaluates a boolean combination of comparison expressions and returns TRUE or FALSE
            
            $eval_expr = function( $expr, $atts ) use ( $eval_comp ) {
                $sum = FALSE;
                $product = TRUE;
                preg_match_all( '/' . REGEX_COMP_EXPR . '(&&|\|\||$)/', $expr, $matches, PREG_SET_ORDER );
                error_log( '$eval_expr():$atts=' . print_r( $atts, true ) );
                foreach ( $matches as $match ) {
                    error_log( '$eval_expr():$match=' . print_r( $match, true ) );
                    if ( !array_key_exists( $match[ 2 ], $atts ) ) {
                        return FALSE;
                    }
                    $value = $atts[ $match[ 2 ] ];
                    if ( !empty( $match[ 10 ] ) ) {
                        # $#alpha#="gamma"
                        # $#alpha#='gamma'
                        $bool_right = $eval_comp( $value, $match[ 4 ], $match[ 10 ] );
                    } else if ( !empty( $match[ 7 ] ) ) {
                        # $#alpha#=$#beta#
                        $right = $match[ 7 ];
                        if ( !array_key_exists( $right, $atts ) ) {
                            return FALSE;
                        }
                        $bool_right = $eval_comp( $value, $match[ 4 ], $atts[ $right ] );
                    } else {
                        # $#alpha#
                        $bool_right = !is_null( $value ) && $value !== '';
                    }
                    error_log( '$bool_right=' . $bool_right );
                    $product = $product && $bool_right;
                    $bool_op = $match[ 12 ];
                    error_log( '$bool_op=' . $bool_op );
                    if ( $bool_op === '||' || !$bool_op ) {
                        $sum = $sum || $product;
                        $product = TRUE;
                    }
                }
                return $sum;
            };   # $eval_expr = function( $expr, $atts ) use ( $eval_comp ) {
            
            static $saved_inline_macros = [ ];
            $error = NULL;
            if ( $TPCTI_MF2_ACTIVE ) {
            $mf_table_custom_groups = MF_TABLE_CUSTOM_GROUPS;   # MF2 only
            $mf_table_custom_fields = MF_TABLE_CUSTOM_FIELDS;   # MF2 only
            $mf_table_post_meta = MF_TABLE_POST_META;           # MF2 only
            }   # if ( $TPCTI_MF2_ACTIVE ) {
            if ( !$atts ) {
                $atts = [ ];
            }
            if ( $macro ) {
                $macro = ltrim( $macro );
            }

            # handle posts iterations

            #if ( $macro ) { $macro = htmlspecialchars_decode( $macro ); }
            # first check if the macro invocation has a post parameter of the form a comma separated list of post ids
            # or related_type fields or alt_related_type fields
            if ( is_array( $atts ) && array_key_exists( 'post', $atts ) ) {
                $att_post = $atts['post'];
                unset( $atts['post'] );
                $result = '';
                foreach ( explode( ';', $att_post ) as $post_id ) {
                    if ( !is_numeric( $post_id ) ) {
                        # post parameter is a related_type field or alt_related_type field   # MF2 only - filter is
                        if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
                        $post_ids = $get_custom_field( $post_id . "@tk_filter_by_type__related_type__alt_related_type", TRUE );
                        } else {   # if ( $TPCTI_MF2_ACTIVE ) {
                        $post_ids = $get_custom_field( $post_id, TRUE );
                        }   # if ( $TPCTI_MF2_ACTIVE ) {
                        if ( $error ) {
                            return $error;
                        }
                    } else {
                        $post_ids = [ $post_id ];
                    }
                    $save_post = $post;
                    foreach ( $post_ids as $post_id1 ) {
                        if ( !$post_id1 ) {
                            continue;
                        }
                        $post = get_post( $post_id1 );
                        $result .= $do_macro( $atts, $macro );
                    }
                    $post = $save_post;
                }
                return $result;
            }

            if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
            
            # do the Magic Fields 2 group or field iterators

            # next check if the macro invocation has an iterator parameter of the format
            # group_iterator="iterator_name:group_name" or field_iterator="iterator_name:field_name<group_index>"
            # only one iterator parameter is allowed per macro invocation but macros can be nested to allow nested iterations
            if ( is_array( $atts ) && ( $group = array_key_exists( 'group_iterator', $atts )
                or $field = array_key_exists( 'field_iterator', $atts ) ) ) {
                if ( $group ) {
                    # find the group indexes
                    list( $iterator_name, $group_name ) = explode( ':', $atts['group_iterator'] );
                    unset( $atts['group_iterator'] );
                    $indexes = $wpdb->get_col( $wpdb->prepare( <<<EOD
SELECT m.group_count FROM $mf_table_custom_groups g, $mf_table_custom_fields f, $mf_table_post_meta m
    WHERE g.id = f.custom_group_id AND f.name = m.field_name
        AND g.name = %s AND g.post_type = '$post->post_type' AND m.post_id = $post->ID
    GROUP BY m.group_count ORDER BY m.group_count
EOD
                    , $group_name ) );
                } else if ( $field ) {
                    # find the field indexes; * for group index means over all groups
                    if ( preg_match( '/^(\w+):(\w+)(<(\*|\d+)>)?$/', $atts['field_iterator'], $matches ) ) {
                        unset( $atts['field_iterator'] );
                        if ( array_key_exists( 4, $matches ) and $matches[4] !== '*' ) {
                            $indexes = $wpdb->get_col( $wpdb->prepare( <<<EOD
SELECT m.field_count FROM $mf_table_post_meta m
    WHERE m.field_name = %s AND m.post_id = $post->ID AND m.group_count = %d ORDER BY m.field_count
EOD
                                , $matches[2], $matches[4] ) );
                        } else {
                            $indexes = $wpdb->get_col( $wpdb->prepare( <<<EOD
SELECT m.field_count FROM $mf_table_post_meta m
    WHERE m.field_name = %s AND m.post_id = $post->ID GROUP BY m.field_count ORDER BY m.field_count
EOD
                                , $matches[2] ) );
                        }
                        $iterator_name = $matches[1];
                    } else {
                    }
                }
/*
                if ( empty( $indexes ) ) {
                    return <<<EOD
<div style="border:2px solid red;padding:5px;">Error: invalid group_iterator or field_iterator parameter</div>
EOD;
                }
*/
                # do the iteration over either the group or field index values
                $result = '';
                foreach ( $indexes as $index ) {
                    $atts[$iterator_name] = $index;
                    $result .= $do_macro( $atts, $macro );
                }
                return $result;
            }
            
            }   # if ( $TPCTI_MF2_ACTIVE ) {

            # do the generic iterators

            # finally check for generic iterator parameter - iterator="name:12345;"abcde";'abcde';alpha<1,1>"
            # "it" is accepted as an abbreviation for "iterator" 
            if ( is_array( $atts )
                && ( ( $iterator = array_key_exists( 'iterator', $atts ) ) || array_key_exists( 'it', $atts ) ) ) {
                if ( ( $ret = preg_match( '#^(\w+):((\s*(\d+|"[^"]*"|\'[^\']*\'|[^;]+)(;|$))+)#',
                    $iterator ? $atts[ 'iterator' ] : $atts[ 'it' ], $matches ) ) === 1 ) {
                    unset( $atts[ 'iterator' ], $atts[ 'it' ] );
                    $iterator_name = $matches[1];
                    if ( ( $ret = preg_match_all( '#(\s*(\d+)|\s*"([^"]*)"|\s*\'([^\']*)\'|\s*([^\s;]+))(;|$)#',
                        $matches[2], $matches1, PREG_SET_ORDER ) ) !== false && $ret !== 0 ) {
                        $iterator_values = [];
                        foreach( $matches1 as $matches2 ) {
                            if ( $matches2[2] ) {          # number
                                $iterator_values[] = $matches2[2];
                            } else if ( $matches2[3] ) {   # double quoted string
                                $iterator_values[] = $matches2[3];
                            } else if ( $matches2[4] ) {   # single quoted string
                                $iterator_values[] = $matches2[4];
                            } else if ( $matches2[5] ) {   # custom field specifier
                                $custom_field = $matches2[5];
                                $iterator_values = array_merge( $iterator_values, $get_custom_field( $custom_field, TRUE ) );
                                if ( $error ) {
                                    return $error;
                                }
                            }
                        }
                    } else {
                        return <<<EOD
<div style="border:2px solid red;color:red;padding:5px;">{$options->shortcode_name} error:
    invalid iterator parameter.</div>
EOD;
                    }           
                } else {
                    return <<<EOD
<div style="border:2px solid red;color:red;padding:5px;">{$options->shortcode_name} error:
    invalid iterator parameter.</div>
EOD;
                }

                # finally do the macro for each iteration value and concatenate the results
                $result = '';
                foreach ( $iterator_values as $iterator_value ) {
                    if ( !$iterator_value && $iterator_value !== "0" ) {
                        continue;
                    }
                    $atts[$iterator_name] = $iterator_value;
                    $result .= $do_macro( $atts, $macro );
                }
                return $result;
            }
            
            # get the template definition
            
            if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
                $name = 'macro';
            } else {   # if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
                $name = 'name';
            }   # } else {   # if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
            if ( !$macro ) {
                if ( !empty( $atts[ $name ] ) ) {
                    if ( !empty( $saved_inline_macros[ $atts[ $name ] ] ) ) {
                        # saved inline macro definitions have priority over Content Macro definitions
                        $macro = $saved_inline_macros[ $atts[ $name ] ];
                    } else {
                        # get macro definition from MySQL table
                        $macro = $wpdb->get_var( $wpdb->prepare(
                            "SELECT post_content from $wpdb->posts WHERE post_type = %s AND post_name = %s",
                                $options->content_macro_post_type, $atts[ $name ] ) );
                        if ( !$macro ) {
                            return <<<EOD
<div style="border:2px solid red;color:red;padding:5px;">{$options->shortcode_name} error:
    {$atts[ $name ]} is not the slug of a Post Content Template.</div>
EOD;
                        }
                    }
                } else {
                    return <<<EOD
<div style="border:2px solid red;color:red;padding:5px;">{$options->shortcode_name} error:
no post content template specified.</div>
EOD;
                }
            } else {
                # There is an inline macro definition
                if ( !empty( $atts['save_inline_macro_as'] ) ) {
                    # save inline macro definition for later use in the same session
                    $saved_inline_macros[$atts['save_inline_macro_as']] = $macro;
                    if ( array_key_exists( 'save_only_no_action', $atts ) ) {
                        # this is a macro definition only - to be invoked in a later show_macro shortcode
                        return;
                    }
                }
            }
            unset( $atts[ $name ] );

            # do the variable assignments

            # scan for defaults of the form <!-- $#alpha# = "beta"; --> or <!-- $#alpha# = 'beta'; -->
            # or <!-- $#alpha# = beta@gamma; --> or (MF2) <!-- $#alpha# = beta<1,1>; -->
            if ( preg_match_all( '/<!--\s*\$#([\w-]+)#\s*=\s*(("([^"]+)")|(\'([^\']+)\')|([^;]+));\s*-->\r?\n?/',
                $macro, $assignments, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
                # find locations of inner macros
                # add "[/show_macro]" terminator since $find_embedded_macros() requires that macro body be terminated
                $inner_macro_ranges = NULL;
                $status = $find_embedded_macros( $macro . "[/{$options->shortcode_name}]", $inner_macro_ranges );
                if ( is_string( $status ) ) {
                    # error detected so just return error message
                    return $status;
                }
                foreach ( array_reverse( $assignments ) as $assignment ) {
                    # skip assignments inside inner macros
                    foreach ( $inner_macro_ranges as $range ) {
                        if ( $assignment[0][1] >= $range[0] && $assignment[0][1] < $range[1] ) {
                            continue 2;
                        }
                    }
                    # do the assignment
                    if ( !array_key_exists( $assignment[1][0], $atts ) ) {
                        if ( array_key_exists( 7, $assignment ) ) {
                            # assignment is to value of custom field
                            $custom_field = $assignment[7][0];
                            $custom_field = preg_replace_callback( '/\$#(\w+)#/', function( $m ) use ( $atts, &$error ) {
                                if ( array_key_exists( $m[1], $atts ) ) {
                                    return $atts[ $m[1] ];
                                } else {
                                    $error = '<div style="border:2px solid red;color:red;padding:5px;">'
                                        . "template variable {$m[1]} is not assigned a value.</div>";
                                }
                                return $m[0];
                            }, $custom_field );
                            if ( $error ) {
                                return $error;
                            }
                            $atts[ $assignment[1][0] ] = $get_custom_field( $custom_field );
                            if ( $error ) {
                                return $error;
                            }
                        } else {
                            # assignment is to a string constant
                            $atts[ $assignment[1][0] ] = preg_replace_callback( '/\$#(\w+)#/', function( $m )
                                use ( $atts, &$error ) {
                                if ( array_key_exists( $m[1], $atts ) ) {
                                    return $atts[ $m[1] ];
                                } else {
                                    $error = '<div style="border:2px solid red;color:red;padding:5px;">'
                                        . "template variable {$m[1]} is not assigned a value.</div>";
                                }
                                return $m[0];
                            }, trim( $assignment[2][0], '"\'' ) );
                            if ( $error ) {
                                return $error;
                            }
                        }
                    } else {
                    }
                    # remove this variable assigment from source
                    $macro = substr_replace( $macro, '', $assignment[0][1], strlen( $assignment[0][0] ) );
                }   # foreach ( array_reverse( $assignments ) as $assignment ) {
            }
            
            # handle conditional text inclusion
            
            # find locations of inner macros
            # add "[/show_macro]" terminator since $find_embedded_macros() requires that macro body be terminated
            $inner_macro_ranges = NULL;
            $status = $find_embedded_macros( $macro . "[/{$options->shortcode_name}]", $inner_macro_ranges );
            if ( is_string( $status ) ) {
                # error detected so just return error message
                return $status;
            }     
            # the condition of if statement is a boolean combination using && and || operators of comparison expressions of the form $#alpha#='gamma',
            # $#alpha#="gamma", $#alpha#=$#beta# or $#alpha# (last case is not really a comparison)
            $if_count = preg_match_all( '/\r?\n?#if\((' . REGEX_COMP_EXPR . '((&&|\|\|)' . REGEX_COMP_EXPR . ')*)\)#\r?\n?/',
                                        $macro, $if_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
            $end_count = preg_match_all( '/\r?\n?#endif#\r?\n?/', $macro, $end_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

            if ( $if_count !== $end_count ) {
                return <<<EOD
<div style="border:2px solid red;color:red;padding:5px;">{$options->shortcode_name} error:
    count of "#if(...)# not equal count of "#endif#"</div>
EOD;
            }   # if ( $if_count !== $end_count ) {

            if ( $inner_macro_ranges && $if_count ) {
                # first remove #if(...)# ... #endif# matches found in embedded inner macros
                $matches = [ $if_matches, $end_matches ];
                foreach ( $matches as &$m ) {
                    $m = array_merge( array_filter( array_map( function( $m ) use ( $inner_macro_ranges ) {
                        foreach ( $inner_macro_ranges as $r ) {
                            if ( $m[0][1] >= $r[0] && $m[0][1] < $r[1] ) {
                                return FALSE;
                            }
                        }
                        return $m;
                    }, $m ) ) );
                }
                unset( $m );
                $if_matches  = $matches[0];
                $end_matches = $matches[1];
                if ( ( $if_count = count( $if_matches ) ) !== ( $end_count = count( $end_matches ) ) ) {
                    return <<<EOD
<div style="border:2px solid red;color:red;padding:5px;">{$options->shortcode_name} error:
    count of "#if(...)# not equal count of "#endif#"</div>
EOD;
                }
            }   # if ( $inner_macro_ranges && $if_count ) {
            
            if ( $if_count ) {
                # do conditional text inclusion/exclusion
                # first evaluate all conditions in all if statements
                $includes = array_map( function( $match ) use ( $atts, $eval_expr, $eval_comp ) {
                    error_log( '$match=' . print_r( $match, true ) );
                    return $eval_expr( $match[ 1 ][ 0 ], $atts );
                }, $if_matches );
                $i = 0;
                while ( $if_matches && $end_matches ) {
                    # find if that matches the first endif
                    while ( $if_matches[$i][0][1] < $end_matches[0][0][1] ) {
                        if ( ++$i == count( $if_matches ) ) {
                            break;
                        }
                    }
                    if ( --$i < 0 ) {
                        # error
                        return <<<EOD
<div style="border:2px solid red;color:red;padding:5px;">{$options->shortcode_name} error:
    unmatched "#endif"</div>
EOD;
                    }
                    $include = $includes[$i];
                    $start0 = $if_matches[$i][0][1];
                    $length0 = ( $end_matches[0][0][1] + strlen( $end_matches[0][0][0] ) ) - $start0;
                    $start1 = $if_matches[$i][0][1] + strlen( $if_matches[$i][0][0] );
                    $length1 = $end_matches[0][0][1] - $start1;
                    if ( preg_match_all( '/\r?\n?#else#\r?\n?/', substr( $macro, $start1 ), $else_matches,
                        PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
                        $the_else_match = NULL;
                        foreach ( $else_matches as $else_match ) {
                            $inner_macro_else = FALSE;
                            if ( $inner_macro_ranges ) {
                                foreach ( $inner_macro_ranges as $r ) {
                                    if ( $else_match[0][1] + $start1 >= $r[0] && $else_match[0][1] + $start1 < $r[1] ) {
                                        $inner_macro_else = TRUE;
                                        break;
                                    }
                                }
                            }
                            if ( !$inner_macro_else ) {
                                $the_else_match = $else_match;
                                break;
                            }
                        }
                        if ( $the_else_match ) {
                            $start2 = $the_else_match[0][1] + $start1;
                            $length1 = $start2 - $start1;
                            $start2 += strlen( $the_else_match[0][0] );
                            $length2 = $end_matches[0][0][1] - $start2;
                        } else {
                            $start2 = $start1;   # irrelevant since $length2 == 0
                            $length2 = 0;
                        }
                    } else {
                        $start2 = $start1;   # irrelevant since $length2 == 0
                        $length2 = 0;
                    }
                    if ( $include ) {
                        # replace with #if($#...#)# clause
                        $macro = substr_replace( $macro, substr( $macro, $start1, $length1 ), $start0, $length0 );
                        $offset = $length1 - $length0;
                    } else {
                        # replace with #else# clause
                        $macro = substr_replace( $macro, substr( $macro, $start2, $length2 ), $start0, $length0 );
                        $offset = $length2 - $length0;
                    }
                    # remove the matched if
                    array_splice( $if_matches, $i, 1 );
                    array_splice( $includes, $i, 1 );
                    # adjust offsets after text replacement
                    for ( $j = $i; $j < count( $if_matches ); ++$j ) {
                        $if_matches[$j][0][1] += $offset;
                        $if_matches[$j][1][1] += $offset;
                    }
                    if ( $i ) {
                        --$i;
                    }
                    #remove the matched endif
                    array_shift( $end_matches );
                    # adjust offsets after text replacement
                    for ( $j = 0; $j < count( $end_matches ); ++$j ) {
                        $end_matches[$j][0][1] += $offset;
                    }
                }   # while ( $if_matches && $end_matches ) {
                if ( $if_matches || $end_matches ) {
                    # error
                    return 'show_macro:Error: unmatched "#if" or "#endif"';
                }
            }   # if ( $if_count ) {

            # do variable substitutions

            # find all variable locations
            if ( preg_match_all( '/\$#(\w+)#/', $macro, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
                # find locations of inner macros
                # add "[/show_macro]" terminator since $find_embedded_macros() requires that macro body be terminated
                $inner_macro_ranges = NULL;
                $status = $find_embedded_macros( $macro . "[/{$options->shortcode_name}]", $inner_macro_ranges );
                if ( is_string( $status ) ) {
                    # error detected so just return error message
                    return $status;
                }
                # remove variable matches that are inside inner macros
                $matches = array_filter( array_map( function( $match ) use ( $inner_macro_ranges ) {
                    foreach ( $inner_macro_ranges as $range ) {
                        if ( $match[0][1] >= $range[0] && $match[0][1] < $range[1] ) {
                            return FALSE;
                        }
                    }
                    return $match;
                }, $matches ) );
                # do variable substitutions on remaining matches in source
                foreach ( array_reverse( $matches ) as $match ) {
                    if ( array_key_exists( $match[1][0], $atts ) ) {
                        $macro = substr_replace( $macro, $atts[$match[1][0]], $match[0][1], strlen( $match[0][0] ) );
                    } else {
                        return '<div style="border:2px solid red;color:red;padding:5px;">'
                            . "template variable {$match[1][0]} is not assigned a value.</div>";
                    }
                }
            }   # if ( preg_match_all( '/\$#(\w+)#/', $macro, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {

            # finally do macro replacements            

            $macro = do_shortcode( $macro );
            return $macro;

        };   # $mf2tk_the_do_macro = $do_macro = function( $atts, $macro )

        # add the shortcodes - show_macro1, ... show_macro9 are needed to handle nested macros
        # as WordPress cannot correctly parse [show_macro][show_macro][/show_macro][/show_macro]
        # this needs to be written as [show_macro][show_macro1][/show_macro1][/show_macro]

        add_shortcode( $options->shortcode_name, $do_macro );
        if ( !empty( $options->shortcode_name_alias ) ) {
            add_shortcode( $options->shortcode_name_alias, $do_macro );
        }
        for ( $i = 1; $i < 9; $i++ ) {
            add_shortcode( $options->shortcode_name . $i, $do_macro );
            if ( !empty( $options->shortcode_name_alias ) ) {
                add_shortcode( $options->shortcode_name_alias . $i, $do_macro );
            }
        }

        # output settings page
        
        add_action( 'admin_init', function( ) use ( $TPCTI_MF2_ACTIVE, $options ) {
            add_settings_section( 'tpcti-options', 'A Tiny Post Content Template Interpreter', function( ) {
?>
The defaults should work for almost all websites but you may want to change some of them in case of conflicts
or for convenience, i.e., to reduce typing. The documentation for this plugin is
<a href="https://tpcti.wordpress.com/" target="_blank">here</a>.
<?php
            }, 'tpcti-settings-page' );
            
            add_settings_field( 'tpcti-options-field', 'Settings', function( ) use ( $TPCTI_MF2_ACTIVE, $options ) {
?>
<table id="tpcti-table">
<tr><th style="text-align:right;">Shortcode Name:</th>
    <td><input type="text" id="shortcode_name" class="tpcti_option" value="<?php echo $options->shortcode_name; ?>"></td>
    <td>global so choose carefully</td></tr>
<tr><th style="text-align:right;">Shortcode Name Alias:</th>
    <td><input type="text" id="shortcode_name_alias" class="tpcti_option" value="<?php echo $options->shortcode_name_alias; ?>"></td>
    <td>global so choose carefully</td></tr>
<tr><th style="text-align:right;">Post Type:</th>
    <td><input type="text" id="content_macro_post_type" class="tpcti_option" maxlength="20" value="<?php echo $options->content_macro_post_type; ?>"></td>
    <td>save templates as this post type - maximum 20 characters</td></tr>
<tr><th style="text-align:right;">Filter Prefix:</th>
    <td><input type="text" id="filter" class="tpcti_option" value="<?php echo $options->filter; ?>"></td>
    <td>precedes filter name in custom field specifier</td></tr>
<tr><th style="text-align:right;">Multi Valued Separator:</th>
    <td><input type="text" id="separator" class="tpcti_option" value="<?php echo $options->separator; ?>"></td>
    <td>use this to separate the values in a multi-valued field</td></tr>
<tr><th style="text-align:right;color:<?php
        if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
            echo 'gray';
        } else {   # if ( $TPCTI_MF2_ACTIVE ) {
            echo 'inherit';
        }   # if ( $TPCTI_MF2_ACTIVE ) {
        ?>;">Post Member Operator:</th>
    <td><input type="text" id="post_member" class="tpcti_option" value=<?php echo "\"{$options->post_member}\"";
        if ( $TPCTI_MF2_ACTIVE && !$options->use_native_mode ) {
            echo 'disabled';
        } else {   # if ( $TPCTI_MF2_ACTIVE ) {
        }   # if ( $TPCTI_MF2_ACTIVE ) {
        ?>></td>
    <td>left of operator is a post id and right of operator is a custom field in that post</tr>
<tr><th style="text-align:right;"><a href="https://tpcti.wordpress.com/#problems" target="_blank">Disable wptexturize</a>:</th>
    <td><input type="checkbox" id="disable_wptexturize" class="tpcti_option" value="wptexturize_disabled"
        <?php if ( $options->disable_wptexturize ) { echo 'checked'; } ?>></td>
    <td>wptexturize may texturize quote marks of shortcode parameters which breaks my parser</tr>
<?php
if ( $TPCTI_MF2_ACTIVE ) {
?>
<tr><th style="text-align:right;">Use <a href="https://tpcti.wordpress.com/" target="_blank">TPCTI Native Mode</a>:</th>
    <td><input type="checkbox" id="use_native_mode" class="tpcti_option" value="native_mode"
        <?php if ( $options->use_native_mode ) { echo 'checked'; } ?>></td>
    <td>not compatible with version 0.5.8 and not recommended</tr>
<?php
}   # if ( $TPCTI_MF2_ACTIVE ) {
?>
</table>
<input type="hidden" id="tpcti_options" name="tpcti_options" value="">
<script>
(function(){
    var tpctiOptionData={};
    jQuery(jQuery("table#tpcti-table")[0].parentNode.parentNode).find(">th").css("display","none");
    jQuery("input.tpcti_option").change(function(){
        if(this.type==="text"){
            tpctiOptionData[this.id]=this.value;
        }else if(this.type==="checkbox"){
            tpctiOptionData[this.id]=jQuery(this).prop("checked");
            if(this.id==="use_native_mode"){
                jQuery("input#post_member").prop("disabled",!jQuery(this).prop("checked"));
                jQuery("input#post_member").parent().parent().find("th")
                    .css("color",jQuery(this).prop("checked")?"inherit":"gray");
            }
        }
        jQuery("input#tpcti_options").val(JSON.stringify(tpctiOptionData));
    });
}());
</script>
<?php
            }, 'tpcti-settings-page', 'tpcti-options' );
            
            register_setting( 'tpcti_options', 'tpcti_options', function( $value ) use ( $options, $TPCTI_MF2_ACTIVE ) {
                if ( is_object( $value ) ) {
                    # this update is from MF2TK so don't sync with MF2TK otherwise you have infinite recursion
                    return $value;
                }
                # this update is from the Settings Admin Page
                $value = ( array ) json_decode( $value );
                if ( $TPCTI_MF2_ACTIVE ) {
                    # synchronize with MF2TK tags
                    $shortcode_name_changed       = array_key_exists( 'shortcode_name',       $value );
                    $shortcode_name_alias_changed = array_key_exists( 'shortcode_name_alias', $value );
                    if ( $shortcode_name_changed || $shortcode_name_alias_changed ) {
                        $mf2tk_tags = mf2tk\get_tags( );
                        if ( $shortcode_name_changed       ) {
                            $mf2tk_tags[ 'show_macro' ]       = $value[ 'shortcode_name' ];
                        }
                        if ( $shortcode_name_alias_changed ) {
                            $mf2tk_tags[ 'show_macro_alias' ] = $value[ 'shortcode_name_alias' ];
                        }
                        # set a flag so MF2TK can tell this update comes from TPCTI
                        $mf2tk_tags[ 'update_from_tpcti' ] = TRUE;
                        update_option( 'magic_fields_2_toolkit_tags', $mf2tk_tags );
                    }
                }   # if ( $TPCTI_MF2_ACTIVE ) {
                return ( object ) array_merge( ( array ) $options, $value );
            } );
        } );
        
        add_action( 'admin_menu', function( ) {
            add_options_page( 'A Tiny Post Content Template Interpreter', 'A Tiny Post Content Template Interpreter',
                'manage_options', 'tpcti-settings-page', function( ) {
                echo '<form method="POST" action="options.php">';
                settings_fields( 'tpcti_options' );
                do_settings_sections( 'tpcti-settings-page' );
                submit_button( );
                echo '</form>';
            } );
        } );

        remove_filter( 'the_content', 'wpautop' );
    };   # $construct = function( ) use ( &$mf2tk_the_do_macro ) {

    $construct( );
}

#if ( $TPCTI_MF2_ACTIVE ) {

namespace mf2tk {

    const TPCTI_VERSION = 1.0;

    if ( $TPCTI_MF2_ACTIVE ) {

    function do_macro( $atts, $macro ) {
        global $mf2tk_the_do_macro;
        require_once( MF_PATH . '/mf_front_end.php' );
        $macro = stripslashes( $macro );
        $result = $mf2tk_the_do_macro( $atts, $macro );
        return $result;
    }
    }   # if ( $TPCTI_MF2_ACTIVE ) {
}

#}   # if ( $TPCTI_MF2_ACTIVE ) {

?>