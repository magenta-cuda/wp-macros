<?php

# this is secure limited expression evaluator - limited means the expression consists of integers and quoted strings joined by *, + and . operators 
# and grouped by possibly nested parenthesis. The operator precedence is given by the order *, + and .. If the result is numeric the value is saved
# as an integer otherwise the value is saved as a string. Invalid expressions 3 + "xyz" evaluate to NULL.

function tti_iii_eval_expr( $expr ) {
    $expr  .= ' ';
    $i      = 0;
    $length = strlen( $expr );
    $result = tti_iii_eval_concatenation( $expr, $i, $length );
    echo '  $expr=\'' . $expr . '\'' . PHP_EOL;
    echo '$length=' . $length . PHP_EOL;
    echo '$result=' . $result . PHP_EOL;
    echo '     $i=' . $i . PHP_EOL;
    echo '---------------------------------------' . PHP_EOL;
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

function tti_iii_eval_sum( $expr, &$i, $length ) {
    $sum      = NULL;
    $sum_mode = TRUE;
    while ( $i < $length ) {
        if ( $sum_mode ) {
            if ( ( $operand = tti_iii_eval_product( $expr, $i, $length ) ) === NULL ) {
                error_log( 'tti_iii_eval_sum()[1]:return NULL' );
                return NULL;
            }
            if ( $sum === NULL ) {
                $sum = $operand;
            } else if ( is_int( $sum ) && is_int( $operand ) ) {
                $sum += $operand;
            } else {
                error_log( 'tti_iii_eval_sum()[2]:return NULL' );
                return NULL;
            }
            $sum_mode = FALSE;
            continue;
        } else {
            $chr = substr( $expr, $i, 1 );
            if ( $chr === '+' ) {
                if ( is_string( $sum ) ) {
                error_log( 'tti_iii_eval_sum()[3]:return NULL' );
                    return NULL;
                }
                $sum_mode = TRUE;
                ++$i;
                continue;
            } else if ( $chr === ')' || $chr === '.' ) {
                return $sum;
            }
        }
        error_log( 'tti_iii_eval_sum()[4]:return NULL' );
        return NULL;
    }
    return $sum;
}

function tti_iii_eval_product( $expr, &$i, $length ) {
    $product      = NULL;
    $product_mode = TRUE;
    $integer_mode = FALSE;
    $string_mode  = FALSE;
    $quote        = NULL;
    $i0           = -1;
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
                } else if ( is_int( $product ) ) {
                    $product *= $operand;
                } else {
                    return NULL;
                }
                $product_mode = FALSE;
                $integer_mode = FALSE;
                $i0           = -1;
                continue;
            }
        } else if ( $string_mode ) {
            if ( $chr === $quote && $chr0 !== '\\' ) {
                $product = str_replace( '\\' . $quote, $quote, substr( $expr, $i0, $i - $i0 ) );
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
        }
        if ( ctype_space( $chr ) ) {
            ++$i;
            continue;
        }
        if ( $chr === '*' ) {
            if ( is_string( $product ) ) {
                return NULL;
            }
            $product_mode = TRUE;
            ++$i;
            continue;
        }
        if ( $chr === ')' || $chr === '+' || $chr === '.' ) {
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

$exprs = [
    '57',
    ' 5',
    '55',
    '5*2',
    '5*2*333',
    '5 * 2 * 333',
    ' 5 * ( 2 * 333 ) ',
    '11 + 88 + 1',
    '11 + 11 * 8 + 1',
    '11+(11*8) +1',
    '(77)'
];
$exprs = [
    '5*2+(50+( 2 * 10 ) + 20)',
    '"aaa" . "xxx\"zzz" . ( 11*3 + ( 2 * 22 ) )'
];

foreach ( $exprs as $expr ) {
    $value = tti_iii_eval_expr( $expr );
}

?>