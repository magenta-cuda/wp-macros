<?php

/*  Copyright 2015  Magenta Cuda

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


# This is secure limited expression evaluator - limited means the expression consists of integers and single or double quoted strings
# joined by '*', '/', '%', '+', '-' and '.' operators and grouped by possibly nested parenthesis. The operator precedence is given by
# the order ( '*', '/', '%' ), ( '+', '-' ), '.' where operators in the same parenthetical group have the same precedence. Note that
# this is slightly different from PHP where '+' and '.' have the same preference. Associativity is always left associative. If the
# result is numeric the value is saved as an integer otherwise the value is saved as a string. Invalid expressions, e.g. 3 + "xyz"
# evaluate to NULL.

function tti_iii_eval_expr( $expr ) {
    $expr  .= ' ';
    $i      = 0;
    $length = strlen( $expr );
    $result = tti_iii_eval_concatenation( $expr, $i, $length );
/*
    # remove below
    echo '  $expr=\'' . $expr . '\'' . PHP_EOL;
    echo '$length=' . $length . PHP_EOL;
    echo '$result=' . $result . PHP_EOL;
    echo '     $i=' . $i . PHP_EOL;
    echo '---------------------------------------' . PHP_EOL;
    # remove above
 */
    return $result;
}

function tti_iii_eval_concatenation( $expr, &$i, $length ) {
    $join      = NULL;
    $join_mode = TRUE;
    while ( $i < $length ) {
        if ( $join_mode ) {
            if ( ( $operand = tti_iii_eval_sum( $expr, $i, $length ) ) === NULL ) {
                error_log( 'tti_iii_eval_concatenation()[1]:return NULL' );
                return NULL;
            }
            if ( $join === NULL ) {
                $join = $operand;
            } else {
                $join .= $operand;
            }
            $join_mode = FALSE;
            continue;
        } else {
            $chr = substr( $expr, $i, 1 );
            if ( $chr === '.' ) {
                $join_mode = TRUE;
                ++$i;
                continue;
            } else if ( $chr === ')' ) {
                return $join;
            }
        }
        error_log( 'tti_iii_eval_concatenation()[4]:return NULL' );
        return NULL;
    }
    return $join;
}

# tti_iii_eval_sum( ) evaluates a sequence of addends joined by either '+' or '-'
# An addend may be a product or a parenthesized expression, e.g. tti_iii_eval_sum( ) considers 5*11 or (5*(2+7)) to be an addend 

function tti_iii_eval_sum( $expr, &$i, $length ) {
    $sum      = NULL;
    $sum_mode = TRUE;
    $operator = NULL;
    while ( $i < $length ) {
        $chr = substr( $expr, $i, 1 );
        if ( $sum_mode ) {
            # handle unary prefix operators
            if ( ctype_space( $chr ) || $chr === '+' ) {
                ++$i;
                continue;
            }
            if ( $chr === '-' ) {
                $operator = $operator === '+' ? '-' : '+';
                ++$i;
                continue;
            }
            if ( ( $operand = tti_iii_eval_product( $expr, $i, $length ) ) === NULL ) {
                error_log( 'tti_iii_eval_sum()[1]:return NULL' );
                return NULL;
            }
            if ( $sum === NULL ) {
                $sum = $operand;
            } else if ( is_integer( $sum ) && is_integer( $operand ) ) {
                if ( $operator === '+' ) {
                    $sum += $operand;
                } else {
                    $sum -= $operand;
                }
            } else {
                error_log( 'tti_iii_eval_sum()[2]:return NULL' );
                return NULL;
            }
            $sum_mode = FALSE;
            $operator = NULL;
            continue;
        } else {
            if ( $chr === '+' || $chr === '-' ) {
                if ( is_string( $sum ) ) {
                    error_log( 'tti_iii_eval_sum()[3]:return NULL' );
                    return NULL;
                }
                $sum_mode = TRUE;
                $operator = $chr;
                ++$i;
                continue;
            } else if ( $chr === ')' || $chr === '.' ) {
                // right parenthesis or operator of lower priority ends the sequence of addends
                return $sum;
            }
        }
        error_log( 'tti_iii_eval_sum()[4]:return NULL' );
        return NULL;
    }
    return $sum;
}

# tti_iii_eval_product( ) evaluates a sequence of multiplier and multiplicands joined by '*' or '/'
# a multiplier or multiplicand may be a parenthesized expression, e.g. tti_iii_eval_product( ) considers (1+11) to be a multiplicand

function tti_iii_eval_product( $expr, &$i, $length, $filter = '@' ) {
    $product       = NULL;
    $product_mode  = TRUE;
    $integer_mode  = FALSE;
    $string_mode   = FALSE;
    $variable_mode = FALSE;
    $quote         = NULL;
    $operator      = NULL;
    $i0            = -1;
    while ( $i < $length ) {
        $chr = substr( $expr, $i, 1 );
        if ( $integer_mode ) {
            if ( ctype_digit( $chr ) ) {
                ++$i;
                continue;
            } else {
                $operand = ( integer ) substr( $expr, $i0, $i - $i0 );
                if ( $product === NULL ) {
                    $product = $operand;
                } else if ( is_integer( $product ) ) {
                    if ( $operator === '*' ) {
                        $product *= $operand;
                    } else if ( $operator === '/' ) {
                        $product = ( integer ) ( $product / $operand );
                        
                    } else {
                        $product %= $operand;
                    }
                } else {
                    return NULL;
                }
                $product_mode = FALSE;
                $integer_mode = FALSE;
                $operator     = NULL;
                $i0           = -1;
                continue;
            }
        } else if ( $string_mode ) {
            if ( $chr === $quote && $chr0 !== '\\' ) {
                if ( $product !== NULL ) {
                    return NULL;
                }
                $product      = str_replace( '\\' . $quote, $quote, substr( $expr, $i0, $i - $i0 ) );
                $product_mode = FALSE;
                $string_mode  = FALSE;
                $quote        = NULL;
                $i0           = -1;
                ++$i;
                continue;
            } else {
                $chr0 = $chr;
                ++$i;
                continue;
            }
        } else if ( $variable_mode ) {
            if ( ctype_alnum( $chr ) || $chr === '_' || $chr === $filter ) {
                ++$i;
                continue;
            } else {
                if ( !is_scalar( $operand = tti_iii_get_custom_field( substr( $expr, $i0, $i - $i0 ) ) ) ) {
                    error_log( 'tti_iii_eval_product()[1]:return NULL' );
                    return NULL;
                }
                if ( is_numeric( $operand ) ) {
                    $operand = ( integer ) $operand;
                }
                if ( $product === NULL ) {
                    $product = $operand;
                } else if ( is_integer( $product ) && is_integer( $operand ) ) {
                    if ( $operator === '*' ) {
                        $product *= $operand;
                    } else if ( $operator === '/' ) {
                        $product = ( integer ) ( $product / $operand );
                        
                    } else {
                        $product %= $operand;
                    }
                } else {
                    error_log( 'tti_iii_eval_product()[2]:return NULL' );
                    return NULL;
                }
                $product_mode  = FALSE;
                $variable_mode = FALSE;
                $operator      = NULL;
                $i0            = -1;
                continue;
            }
        } else if ( ctype_digit( $chr ) ) {
            if ( $product_mode === FALSE ) {
                return NULL;
            }
            $integer_mode = TRUE;
            $i0           = $i;
            ++$i;
            continue;
        } else if ( $chr === '\'' || $chr === '"' ) {
            if ( $product_mode === FALSE ) {
                return NULL;
            }
            $quote       = $chr;
            $chr0        = $chr;
            ++$i;
            $i0          = $i;
            $string_mode = TRUE;
            continue;
        } else if ( ctype_alpha( $chr ) ) {
            $i0            = $i;
            $variable_mode = TRUE;
            ++$i;
            continue;
        }
        if ( ctype_space( $chr ) ) {
            ++$i;
            continue;
        }
        if ( $chr === '*' || $chr === '/' || $chr === '%' ) {
            if ( is_string( $product ) ) {
                return NULL;
            }
            $product_mode = TRUE;
            $operator     = $chr;
            ++$i;
            continue;
        }
        if ( $chr === ')' || $chr === '+' || $chr === '-' || $chr === '.' ) {
            // right parenthesis or operators of lower priority ends the sequence of multiplicands
            break;
        }
        if ( $chr === '(' ) {
            if ( $product_mode ) {
                ++$i;
                if ( ( $operand = tti_iii_eval_concatenation( $expr, $i, $length ) ) && substr_compare( $expr, ')', $i, 1 ) === 0 ) {
                    if ( $product === NULL ) {
                        $product = $operand;
                    } else if ( is_int( $product ) && is_int( $operand ) ) {
                        $product *= $operand;
                    } else {
                        return NULL;
                    }
                    $product_mode = FALSE;
                    ++$i;
                    continue;
                }
            }
        }
        return NULL;
    }
    return $product;
}

?>