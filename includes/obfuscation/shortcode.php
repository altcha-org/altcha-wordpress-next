<?php

if (! defined('ABSPATH')) exit;

add_action('init', 'altcha_register_obfuscate_shortcode');

function altcha_register_obfuscate_shortcode(): void
{
    add_shortcode('obfuscate', 'altcha_obfuscate_shortcode_handler');
}

function altcha_obfuscate_shortcode_handler($atts, $content = null): string
{
    $atts = shortcode_atts(
        array(
            'message' => '',
            'text' => '',
            'email' => '',
            'mail' => '',
            'telephone' => '',
            'tel' => '',
            'title' => '',
            'class' => '',
        ),
        $atts,
        'obfuscate'
    );

    if (empty($atts['title'])) {
        return '';
    }
    $title = esc_attr($atts['title']);
    $class = ! empty($atts['class']) ? ' ' . esc_attr($atts['class']) : '';

    $obfuscated = '';

    if (!empty($atts['message']) || !empty($atts['text'])) {
        $content = ! empty($atts['message']) ? $atts['message'] : $atts['text'];
        $obfuscated = obfuscateText($content);
    } elseif (! empty($atts['email']) || ! empty($atts['mail'])) {
        $content = ! empty($atts['email']) ? $atts['email'] : $atts['mail'];
        $obfuscated = obfuscateMail($content);
    } elseif (! empty($atts['telephone']) || ! empty($atts['tel'])) {
        $content = ! empty($atts['telephone']) ? $atts['telephone'] : $atts['tel'];
        $obfuscated = obfuscateTelephone($content);
    }

    if (empty($obfuscated)) {
        return '';
    }

    if (function_exists('altcha_enqueue_widget_scripts')) {
        altcha_enqueue_widget_scripts();
    }

    return obfuscateWidget($obfuscated, $title, $class);
}

function obfuscateWidget(string $obfuscatedData, string $title, string $class = ''): string
{
    return '<altcha-widget ' .
        'obfuscated="' . $obfuscatedData . '" ' .
        'floating >' .
        '<button class="altcha-obfuscation-button' . $class . '">' . $title . '</button>' .
        '</altcha-widget>';
}
