<?php

function tti_iii_eval_expr( $expr, &$i, $length ) {
    return tti_iii_eval_product( $expr, $i, $length );
}

function tti_iii_eval_product( $expr, &$i, $length ) {
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
        if ( $chr === ')' ) {
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
            return NULL; 
        } 
    }
    if ( $product_mode && $i0 !== -1 ) {
        error_log( 'substr( $expr, $i0, $i - $i0 )=' . substr( $expr, $i0, $i - $i0 ) );
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
    ' '
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