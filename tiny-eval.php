<?php

# this is secure limited expression evaluator 

function tti_iii_eval_expr( $expr, &$i, $length ) {
    return tti_iii_eval_sum( $expr, $i, $length );
}

function tti_iii_eval_sum( $expr, &$i, $length ) {
    error_log( 'tti_iii_eval_sum():0:substr($expr,$i)=' . substr( $expr, $i ) );
    $sum      = 0;
    $sum_mode = TRUE;
    while ( $i < $length ) {
        if ( $sum_mode ) {
            $operand = tti_iii_eval_product( $expr, $i, $length );
            error_log( 'tti_iii_eval_sum():1:substr($expr,$i)=' . substr( $expr, $i ) );
            if ( $operand ) {
                $sum += $operand;
                $sum_mode = FALSE;
                continue;
            }
        } else {
            $chr = substr( $expr, $i, 1 );
            if ( $chr === '+' ) {
                $sum_mode = TRUE;
                ++$i;
                continue;
            } else if ( $chr === ')' ) {
                return $sum;
            }
        }
        error_log( 'tti_iii_eval_sum():2:substr($expr,$i)=' . substr( $expr, $i ) );
        return NULL;
    }
    return $sum;
}

function tti_iii_eval_product( $expr, &$i, $length ) {
    error_log( 'tti_iii_eval_product():0:substr($expr,$i)=' . substr( $expr, $i ) );
    $product      = 1;
    $product_mode = TRUE;
    $digit_mode   = FALSE;
    $i0           = -1;
    while ( $i < $length ) {
        $chr = substr( $expr, $i, 1 );
        if ( $digit_mode ) {
            if ( ctype_digit( $chr ) ) {
                ++$i;
                continue;
            } else {
                $product *= ( integer ) substr( $expr, $i0, $i - $i0 );
                $product_mode = FALSE;
                $i0 = -1;
            }
        } else {
            if ( ctype_digit( $chr ) ) {
                $digit_mode = TRUE;
                $i0 = $i;
                ++$i;
                continue;
            }
        }
        $digit_mode = FALSE;
        if ( ctype_space( $chr ) ) {
            ++$i;
            continue;
        }
        if ( $chr === '*' ) {
            $product_mode = TRUE;
            ++$i;
            continue;
        }
        if ( $chr === ')' || $chr === '+' ) {
            break;
        }
        if ( $chr === '(' ) {
            if ( $product_mode ) {
                ++$i;
                if ( ( $operand = tti_iii_eval_expr( $expr, $i, $length ) ) && substr_compare( $expr, ')', $i, 1 ) === 0 ) {
                    $product *= ( integer ) $operand;
                    $product_mode = FALSE;
                    ++$i;
                    continue;
                }
            }
        }
        return NULL;
    }
    if ( $product_mode && $i0 !== -1 ) {
        $product *= ( integer ) substr( $expr, $i0, $i - $i0 );
    }
    return $product;
}

$exprs = [
    '5',
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