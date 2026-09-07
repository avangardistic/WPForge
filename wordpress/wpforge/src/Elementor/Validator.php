<?php
namespace WPForge\Elementor;

/**
 * Elementor data validator — validate Elementor document structures.
 */
class Validator
{
    /**
     * Validate an Elementor data structure.
     */
    public static function validateData(array $data): \WP_Error|bool
    {
        $errors = new \WP_Error();

        if (empty($data)) {
            return $errors->add('empty_data', 'Elementor data cannot be empty.');
        }

        foreach ($data as $index => $section) {
            $sectionErrors = self::validateSection($section, "section[{$index}]");
            if ($sectionErrors instanceof \WP_Error) {
                foreach ($sectionErrors->get_error_messages() as $msg) {
                    $errors->add('invalid_element', $msg);
                }
            }
        }

        if (count($errors->get_error_messages()) > 0) {
            return $errors;
        }

        return true;
    }

    /**
     * Validate a single Elementor section.
     */
    public static function validateSection(array $section, string $path = 'root'): \WP_Error|bool
    {
        $errors = new \WP_Error();

        if (empty($section['id'])) {
            $errors->add('missing_id', $path . ': Missing element ID.');
        }

        $validTypes = ['section', 'column', 'widget', 'container'];
        if (!isset($section['elType']) || !in_array($section['elType'], $validTypes, true)) {
            $errors->add('invalid_type', $path . ': Invalid element type "' . ($section['elType'] ?? 'none') . '".');
        }

        if (isset($section['elements']) && is_array($section['elements'])) {
            foreach ($section['elements'] as $i => $child) {
                $childResult = self::validateSection($child, $path . ".elements[{$i}]");
                if ($childResult instanceof \WP_Error) {
                    foreach ($childResult->get_error_messages() as $msg) {
                        $errors->add('invalid_child', $msg);
                    }
                }
            }
        }

        return count($errors->get_error_messages()) > 0 ? $errors : true;
    }

    /**
     * Validate an Elementor document ID.
     */
    public static function validateDocumentId(int $id): \WP_Error|bool
    {
        if ($id <= 0) {
            return new \WP_Error('invalid_id', 'Document ID must be a positive integer.');
        }

        $post = get_post($id);
        if (!$post) {
            return new \WP_Error('not_found', 'Document not found.');
        }

        $editMode = get_post_meta($id, '_elementor_edit_mode', true);
        if ($editMode !== 'builder') {
            return new \WP_Error('not_elementor', 'Post is not an Elementor document.');
        }

        return true;
    }

    /**
     * Validate a template type string.
     */
    public static function validateTemplateType(string $type): \WP_Error|bool
    {
        $valid = ['page', 'section', 'header', 'footer', 'single', 'archive', 'error_404', 'search'];
        if (!in_array($type, $valid, true)) {
            return new \WP_Error('invalid_type', 'Invalid template type: ' . $type);
        }
        return true;
    }
}
