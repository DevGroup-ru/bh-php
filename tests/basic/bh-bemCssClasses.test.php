<?php

use BEM\BH;

class bh_bemCssClasses extends PHPUnit_Framework_TestCase {

    function test_itShouldParseCssClasses () {
        $this->assertEquals(
            [ 'block' => 'button',
              'blockMod' => 'disabled',
              'blockModVal' => true,
              'elem' => 'control',
              'elemMod' => null,
              'elemModVal' => null],
            BH::parseBemCssClasses('button_disabled__control')
        );

        $this->assertEquals(
            [ 'block' => 'button',
                'blockMod' => 'mod',
                'blockModVal' => 'val',
                'elem' => 'elem',
                'elemMod' => 'modelem',
                'elemModVal' => 'valelem'],
            BH::parseBemCssClasses('button_mod_val__elem_modelem_valelem')
        );

        $this->assertEquals(
            [ 'block' => 'button',
                'blockMod' => 'disabled',
                'blockModVal' => true,
                'elem' => 'control',
                'elemMod' => null,
                'elemModVal' => null],
            BH::parseBemCssClasses('button--disabled__control', '--')
        );

        $this->assertEquals(
            [ 'block' => 'button',
                'blockMod' => 'mod',
                'blockModVal' => 'val',
                'elem' => 'elem',
                'elemMod' => 'modelem',
                'elemModVal' => 'valelem'],
            BH::parseBemCssClasses('button--mod_val__elem--modelem_valelem', '--')
        );

        $this->assertEquals(
            [ 'block' => 'button',
                'blockMod' => null,
                'blockModVal' => null,],
            BH::parseBemCssClasses('button', '--')
        );
        $this->assertEquals(
            [ 'block' => 'button',
                'blockMod' => null,
                'blockModVal' => null,
            'elem' => 'control',
            'elemMod' => 'type',
            'elemModVal' => 'span'],
            BH::parseBemCssClasses('button__control_type_span', '_')
        );
        $this->assertEquals(
            [ 'block' => 'button',
                'blockMod' => null,
                'blockModVal' => null,
                'elem' => 'control',
                'elemMod' => 'type',
                'elemModVal' => true],
            BH::parseBemCssClasses('button__control_type')
        );
        $this->assertEquals(
            [ 'block' => 'button',
                'blockMod' => null,
                'blockModVal' => null,
                'elem' => 'control',
                'elemMod' => null,
                'elemModVal' => null],
            BH::parseBemCssClasses('button__control')
        );

    }

}
