<?php
namespace WPForge\Elementor;

/**
 * Elementor content parser — extract structured data from Elementor JSON.
 */
class ContentParser
{
    /**
     * Parse raw Elementor JSON data into a structured summary.
     */
    public function parse(array $elementorData): array
    {
        if (empty($elementorData)) {
            return ['elements' => [], 'stats' => $this->getStats([])];
        }

        $elements = [];
        foreach ($elementorData as $section) {
            $elements[] = $this->parseElement($section);
        }

        return [
            'elements' => $elements,
            'stats'    => $this->getStats($elementorData),
        ];
    }

    /**
     * Extract all widget types used in the document.
     */
    public function extractWidgetTypes(array $elementorData): array
    {
        $types = [];
        $this->walkElements($elementorData, function ($element) use (&$types) {
            if (isset($element['widgetType'])) {
                $types[$element['widgetType']] = ($types[$element['widgetType']] ?? 0) + 1;
            }
        });
        return $types;
    }

    /**
     * Extract text content from all elements.
     */
    public function extractTextContent(array $elementorData): array
    {
        $texts = [];
        $this->walkElements($elementorData, function ($element) use (&$texts) {
            $settings = $element['settings'] ?? [];
            foreach (['editor', 'title_text', 'description_text', 'text'] as $key) {
                if (!empty($settings[$key]) && is_string($settings[$key])) {
                    $texts[] = [
                        'type'  => $element['widgetType'] ?? $element['elType'] ?? 'unknown',
                        'key'   => $key,
                        'value' => wp_strip_all_tags($settings[$key]),
                    ];
                }
            }
        });
        return $texts;
    }

    /* ------------------------------------------------------------------ */

    private function parseElement(array $element): array
    {
        $parsed = [
            'id'      => $element['id'] ?? '',
            'type'    => $element['elType'] ?? 'unknown',
            'is_inner' => !empty($element['isInner']),
        ];

        if (isset($element['widgetType'])) {
            $parsed['widget_type'] = $element['widgetType'];
        }

        if (isset($element['settings'])) {
            $parsed['has_settings'] = true;
            $parsed['settings_keys'] = array_keys($element['settings']);
        }

        if (isset($element['elements']) && is_array($element['elements'])) {
            $parsed['children'] = [];
            foreach ($element['elements'] as $child) {
                $parsed['children'][] = $this->parseElement($child);
            }
        }

        return $parsed;
    }

    private function walkElements(array $elements, callable $callback): void
    {
        foreach ($elements as $element) {
            $callback($element);
            if (isset($element['elements']) && is_array($element['elements'])) {
                $this->walkElements($element['elements'], $callback);
            }
        }
    }

    private function getStats(array $data): array
    {
        $stats = [
            'total_sections'  => 0,
            'total_columns'   => 0,
            'total_widgets'   => 0,
            'widget_types'    => [],
        ];

        $this->walkElements($data, function ($element) use (&$stats) {
            $type = $element['elType'] ?? '';
            if ($type === 'section') {
                $stats['total_sections']++;
            } elseif ($type === 'column') {
                $stats['total_columns']++;
            } elseif ($type === 'widget') {
                $stats['total_widgets']++;
                $widgetType = $element['widgetType'] ?? 'unknown';
                $stats['widget_types'][$widgetType] = ($stats['widget_types'][$widgetType] ?? 0) + 1;
            }
        });

        return $stats;
    }
}
