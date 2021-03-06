<?php

require 'tiny-eval.php';

if ( php_sapi_name( ) !== 'cli' ) {
    return;
}

function tti_iii_eval_expr_debug( $expr ) {
    $expr  .= ' ';
    $i      = 0;
    $length = strlen( $expr );
    $result = \TTI_III\tti_iii_eval_concatenation( $expr, $i, $length );
    echo '  $expr=\'' . $expr . '\'' . PHP_EOL;
    echo '$length=' . $length . PHP_EOL;
    echo '$result=' . $result . PHP_EOL;
    echo '     $i=' . $i . PHP_EOL;
    echo '---------------------------------------' . PHP_EOL;
    return $result;
}


if ( count( $argv ) > 1 ) {
    $get_custom_field = function( $field ) {
        echo $field . '> ';
        return trim( fgets( STDIN ) );
    };
    \TTI_III\tti_iii_get_custom_field( $get_custom_field );
    echo tti_iii_eval_expr_debug( $argv[ 1 ] );
    return;
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
    '"aaa" . "xxx\"zzz" . ( 11*3 + ( 2 * 22 ) )',
    "'AAA' . ( ( 111 + ( 222*3 ) ) . 'ZZZ') . 888",
    "'AAA' . ( ( 111 + ( 222*3 ) ) . 'ZZZ') . 111+777",
    "77/11-4",
    "16/5",
    "94%8%5"
];

foreach ( $exprs as $expr ) {
    echo '  expr="' . $expr . '"';
    echo 'result='  . tti_iii_eval_expr_debug( $expr );
}

?>
