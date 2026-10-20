<?php

namespace App\AiWeekly;

class ResponseSchemas
{
    /**
     * @param  array<int, string>  $slugs
     * @return array<string, mixed>
     */
    public static function analysis(array $slugs): array
    {
        $properties = ['summary' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 3000]];
        foreach (['key_points' => 10, 'technologies' => 20, 'companies' => 20, 'opportunities' => 10, 'risks' => 10] as $field => $limit) {
            $properties[$field] = ['type' => 'array', 'maxItems' => $limit, 'items' => ['type' => 'string', 'maxLength' => in_array($field, ['technologies', 'companies'], true) ? 100 : 500]];
        }
        $properties['topics'] = [
            'type' => 'array', 'maxItems' => $slugs === [] ? 0 : 10,
            'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['slug', 'confidence'],
                'properties' => ['slug' => ['type' => 'string', 'enum' => $slugs ?: ['']], 'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1]],
            ],
        ];

        return ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($properties), 'properties' => $properties];
    }

    /**
     * @param  array<int, int>  $articleIds
     * @return array<string, mixed>
     */
    public static function review(array $articleIds): array
    {
        $properties = [
            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
            'excerpt' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
            'markdown_content' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50000],
            'citation_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'integer', 'enum' => array_values($articleIds)]],
            'suggested_tags' => ['type' => 'array', 'maxItems' => 10, 'items' => ['type' => 'string', 'maxLength' => 100]],
        ];

        return ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($properties), 'properties' => $properties];
    }
}
