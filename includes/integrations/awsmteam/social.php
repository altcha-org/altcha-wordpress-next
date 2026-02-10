<?php

if (! defined("ABSPATH")) exit;

add_filter('the_content', 'altcha_awsmteam_social_filter', 99);
add_filter('do_shortcode_tag', 'altcha_awsmteam_shortcode_filter', 10, 4);

function altcha_awsmteam_get_member_id_from_node(DOMElement $node)
{
    $current = $node;
    while ($current instanceof DOMElement) {
        if ($current->hasAttribute('id')) {
            $id = $current->getAttribute('id');
            if (preg_match('/^awsm-member-(\d+)-(\d+)$/', $id, $matches)) {
                return (int)$matches[2];
            }
            if (preg_match('/^awsm-member-(\d+)$/', $id, $matches)) {
                return (int)$matches[1];
            }
        }
        $current = $current->parentNode;
    }

    return 0;
}

function altcha_awsmteam_get_member_obfuscation_settings($member_id)
{
    $default_label = 'Click to view Socials';
    if (!$member_id) {
        return [
            'enabled' => true,
            'label' => $default_label,
        ];
    }

    $enabled_raw = get_post_meta($member_id, '_altcha_obfuscation_enabled', true);
    $enabled = $enabled_raw === '' ? true : (bool)intval($enabled_raw);
    $label = get_post_meta($member_id, '_altcha_obfuscation_label', true);
    $label = is_string($label) ? trim($label) : '';
    if ($label === '') {
        $label = $default_label;
    }

    return [
        'enabled' => $enabled,
        'label' => sanitize_text_field($label),
    ];
}

function altcha_awsmteam_enqueue_assets()
{
    static $enqueued = false;
    if ($enqueued) {
        return;
    }
    $enqueued = true;
    altcha_enqueue_obfuscation_scripts();
    altcha_enqueue_widget_scripts();
    altcha_ensure_obfuscation_script_order();
        wp_enqueue_script(
            'altcha-awsmteam',
            ALTCHA_PLUGIN_URL . 'public/integrations/awsmteam.js',
            ['altcha-obfuscation', 'altcha-widget'],
            '1.0',
            true
        );
}

function altcha_awsmteam_shortcode_filter($output, $tag, $atts, $m)
{
    if ($tag !== 'awsmteam') {
        return $output;
    }
    return altcha_awsmteam_social_replace($output);
}

function altcha_awsmteam_social_filter($content)
{
    return altcha_awsmteam_social_replace($content);
}

function altcha_awsmteam_social_replace($content)
{
    if (strpos($content, 'awsm-social-icons') === false) {
        return $content;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $xpath = new DOMXPath($dom);
    $socialBlocks = $xpath->query("//*[contains(@class, 'awsm-social-icons')]");

    foreach ($socialBlocks as $block) {
        $member_id = altcha_awsmteam_get_member_id_from_node($block);
        $settings = altcha_awsmteam_get_member_obfuscation_settings($member_id);
        if (!$settings['enabled']) {
            continue;
        }

        $links = [];
        $aTags = $xpath->query(".//a[i]", $block);
        foreach ($aTags as $aTag) {
            /** @var DOMElement $aTag */
            $href = $aTag->getAttribute('href');
            $iTag = $xpath->query("i", $aTag)->item(0);
            if ($iTag) {
                /** @var DOMElement $iTag */
                $classAttr = $iTag->getAttribute('class');
                if (preg_match('/\bawsm-icon-[^\s"\'<>]+\b/', $classAttr, $classMatch)) {
                    $iconClass = $classMatch[0];
                } else {
                    $classes = explode(' ', $classAttr);
                    $iconClass = $classes[0] ?? '';
                }
                $links[] = ['icon' => $iconClass, 'url' => $href];
            }
        }

        if (!empty($links)) {
            $json = json_encode($links);
            $obfuscated = AltchaObfuscation::obfuscate($json);
            if ($obfuscated !== '') {
                altcha_awsmteam_enqueue_assets();
                $widget = $dom->createElement('altcha-widget');
                $widget->setAttribute('data-social', '');
                $widget->setAttribute('obfuscated', esc_attr($obfuscated));
                $widget->setAttribute('floating', '');
                $button = $dom->createElement('button', $settings['label']);
                $button->setAttribute('class', 'altcha-obfuscation-button');
                $widget->appendChild($button);
                $block->parentNode->replaceChild($widget, $block);
            }
        }
    }

    return $dom->saveHTML();
}
