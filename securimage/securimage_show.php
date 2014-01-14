<?php
/**
 * Project:     Securimage: A PHP class for creating and managing form CAPTCHA images<br />
 * File:        securimage_show.php<br />
 *
 * Copyright (c) 2011, Drew Phillips
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *  - Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 'AS IS'
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * Any modifications to the library should be indicated clearly in the source code
 * to inform users that the changes are not a part of the original software.<br /><br />
 *
 * If you found this script useful, please take a quick moment to rate it.<br />
 * http://www.hotscripts.com/rate/49400.html  Thanks.
 *
 * @link http://www.phpcaptcha.org Securimage PHP CAPTCHA
 * @link http://www.phpcaptcha.org/latest.zip Download Latest Version
 * @link http://www.phpcaptcha.org/Securimage_Docs/ Online Documentation
 * @copyright 2011 Drew Phillips
 * @author Drew Phillips <drew@drew-phillips.com>
 * @version 3.0 (October 2011)
 * @package Securimage
 *
 */

// Remove the '//' from the following line for debugging problems
 error_reporting(E_ALL); ini_set('display_errors', 1);

// Start the securimage class
require_once dirname( __FILE__ ) . '/securimage.php';
$img = new securimage();

// Set default values for all options here so we can re-use them later
$defaults = array(
        'code_length'                    => rand(5,6),
        'captcha_type'                   => Securimage::SI_CAPTCHA_STRING,
        'image_width'                    => 215,
        'image_height'                   => 80,
        'noise_level'                    => 0,
        'image_bg_color'                 => '#ffffff',
        'text_color'                     => '#707070',
        'line_color'                     => '#707070',
        'signature_color'                => '#707070',
        'noise_color'                    => '#707070',
        'use_wordlist'                   => FALSE,
        'case_sensitive'                 => FALSE,
        'use_transparent_text'           => FALSE,
        'image_type'                     => 'SI_IMAGE_PNG',
        'text_transparency_percentage'   => 30,
        'num_lines'                      => 8,
        'image_signature'                => '',
        'perturbation'                   => 0.75,
);
$settings = array();

// Cycle through values set
foreach ( $_GET as $key => $value ) {
    $setvalue = '';
    switch ( $key ) {
        case 'code_length':
            $clean = preg_replace( '/\D/', '', $value );
            $setvalue = ($clean >= 1) ? $clean : $defaults[$key];
            break;

        case 'captcha_type':
            $setvalue = ($value == 'math') ? Securimage::SI_CAPTCHA_MATHEMATIC : Securimage::SI_CAPTCHA_STRING;
            break;

        case 'image_width':
        case 'image_height':
            $clean = preg_replace( '/\D/', '', $value );
            $setvalue = ($clean >= 20) ? $clean : $defaults[$key];
            break;

        case 'image_bg_color':
        case 'text_color':
        case 'line_color':
        case 'signature_color':
        case 'noise_color':
            $clean = preg_replace( '[^A-Za-z0-9]', '', $value );
            $clean = ($clean) ? '#'.$clean : $defaults[$key];
            $setvalue = new Securimage_Color( $clean );
            break;

        case 'use_wordlist':
        case 'case_sensitive':
        case 'use_transparent_text':
            if ( empty( $value ) ) {
                $setvalue = $defaults[$key];
            } else {
                $setvalue = ($value == '1') ? TRUE : FALSE;
            }
            break;

        case 'image_type':
            switch ( $value ) {
                case 'jpg':
                    $setvalue = 'SI_IMAGE_JPEG';
                    break;
                case 'gif':
                    $setvalue = 'SI_IMAGE_GIF';
                    break;
                case 'png':
                    $setvalue = 'SI_IMAGE_PNG';
                    break;
                default:
                    $setvalue = $defaults[$key];
                    break;
            }
            break;

        case 'text_transparency_percentage':
            $clean = preg_replace( '/\D/', '', $value );
            $setvalue = ($clean >= 0 && $clean <= 100) ? $clean : $defaults[$key]; // 0 = opaque; 100 = transparent;
            break;

        case 'num_lines':
            $clean = preg_replace( '/\D/','',$value );
            $setvalue = ($clean >= 0 && $clean <= 20) ? ($clean) : $defaults[$key];
            break;

        case 'image_signature':
            $setvalue = ($value !== '') ? $value : $defaults[$key];
            break;

        case 'noise_level':
            $clean = preg_replace( '/\D/','',$value );
            $setvalue = ($clean >= 0 && $clean <= 10) ? ($clean) : $defaults[$key];
            break;

        case 'perturbation':
            $clean = preg_replace( '/\D/','',$value );
            $setvalue = ($clean >= 0) ? ($clean / 100) : $defaults[$key];
            break;
        default:
            $setvalue = ($value) ? $value : '';
    }

    $settings[$key] = $setvalue;
}



// Code Settings
$img->code_length                     = (isset($settings['code_length'])) ? $settings['code_length'] : $defaults['code_length'];
$img->use_wordlist                    = (isset($settings['use_wordlist'])) ? $settings['use_wordlist'] : $defaults['use_wordlist'];
$img->case_sensitive                  = (isset($settings['case_sensitive'])) ? $settings['case_sensitive'] : $defaults['case_sensitive'];
$img->captcha_type                    = (isset($settings['captcha_type'])) ? $settings['captcha_type'] : $defaults['captcha_type'];
// Image Settings
$img->image_width                     = (isset($settings['image_width'])) ? $settings['image_width'] : $defaults['image_width'];
$img->image_height                    = (isset($settings['image_height'])) ? $settings['image_height'] : $defaults['image_height'];
$img->image_bg_color                  = (isset($settings['image_bg_color'])) ? $settings['image_bg_color'] : new Securimage_Color( $defaults['image_bg_color'] );
$img->image_type                      = (isset($settings['image_type'])) ? $settings['image_type'] : $defaults['image_type'];
// Text Settings
$img->use_transparent_text            = (isset($settings['use_transparent_text'])) ? $settings['use_transparent_text'] : $defaults['use_transparent_text'];
$img->text_transparency_percentage    = (isset($settings['text_transparency_percentage'])) ? $settings['text_transparency_percentage'] : $defaults['text_transparency_percentage'];
$img->text_color                      = (isset($settings['text_color'])) ? $settings['text_color'] : new Securimage_Color( $defaults['text_color'] );
// Line Settings
$img->num_lines                       = (isset($settings['num_lines'])) ? $settings['num_lines'] : $defaults['num_lines'];
$img->line_color                      = (isset($settings['line_color'])) ? $settings['line_color'] : new Securimage_Color( $defaults['line_color'] );
// Signature Settings - REMOVED DUE TO IT BREAKING THE CAPTCHA
//$img->image_signature                 = (isset($settings['image_signature'])) ? $settings['image_signature'] : $defaults['image_signature'];
//$img->signature_color                 = (isset($settings['signature_color'])) ? $settings['signature_color'] : new Securimage_Color( $defaults['signature_color'] );
// Noise Settings
$img->noise_level                     = (isset($settings['noise_level'])) ? $settings['noise_level'] : $defaults['noise_level'];
$img->noise_color                     = (isset($settings['noise_color'])) ? $settings['noise_color'] : new Securimage_Color( $defaults['noise_color'] );
// Perturbation
$img->perturbation                    = (isset($settings['perturbation'])) ? $settings['perturbation'] : $defaults['perturbation'];

$img->show();  // outputs the image and content headers to the browser
