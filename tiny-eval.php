<?php

# this is secure limited expression evaluator 

function tti_iii_eval_expr( $expr, &$i, $length ) {
    return tti_iii_eval_sum( $expr, $i, $length );
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
            error_log( 'tti_iii_eval_sum():$operand=' . $operand );
            error_log( 'tti_iii_eval_sum():$sum=' . $sum );
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
    error_log( '$sum=' . $sum );
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
                    $product = is_numeric( $operand ) ? ( integer ) $operand : $operand;
                    error_log( '$product=' . $product );
                } else if ( is_int( $product ) ) {
                    $product *= ( integer ) substr( $expr, $i0, $i - $i0 );
                } else {
                    return NULL;
                }
                $product_mode = FALSE;
                $integer_mode = FALSE;
                $i0           = -1;
            }
        } else if ( $string_mode ) {
            if ( $chr === $quote && $chr0 !== '\\' ) {
                $product = str_replace( '\\' . $quote, $quote, substr( $expr, $i0, $i - $i0 ) );
                $product_mode = FALSE;
                $string_mode  = FALSE;
                $quote        = NULL;
                $i0           = -1;
                ++$i;
                error_log( '$product[3]=' . $product );
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
            $i0 = $i;
            ++$i;
            continue;
        } else if ( $chr === '\'' || $chr === '"' ) {
            if ( $product_mode === FALSE ) {
                return NULL;
            }
            $quote = $chr;
            $chr0  = $chr;
            ++$i;
            $i0    = $i;
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
                if ( ( $operand = tti_iii_eval_expr( $expr, $i, $length ) ) && substr_compare( $expr, ')', $i, 1 ) === 0 ) {
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
    error_log( '$product_mode=' . $product_mode );
    error_log( '$i0=' . $i0 );
    if ( $product_mode ) {
        if ( $i0 === -1 ) {
            return NULL;
        }
        $operand = substr( $expr, $i0, $i - $i0 );
        if ( $product === NULL ) {
            $product = is_numeric( $operand ) ? ( integer ) $operand : $operand;
        } else if ( is_int( $product ) && is_int( $operand ) ) {
            $product *= ( integer ) $operand;
        } else {
            return NULL;
        }
    }
    error_log( '$product=' . $product );
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
    '"aaaaaaa"'
];

foreach ( $exprs as $expr ) {
    $i = 0;
    $length = strlen( $expr );
    $value = tti_iii_eval_expr( $expr, $i, $length );
    echo '  $expr=\'' . $expr . '\'' . PHP_EOL;
    echo '$length=' . $length . PHP_EOL;
    echo ' $value=' . $value . PHP_EOL;
    echo '     $i=' . $i . PHP_EOL;
    echo '---------------------------------------' . PHP_EOL;
}

?>