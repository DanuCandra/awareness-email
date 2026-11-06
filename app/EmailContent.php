<?php

namespace App;

class EmailContent
{
    /** @return array<string,array<int,mixed>> */
    public static function rules(string $prefix = 'delta', bool $required = true): array
    {
        $rules = [
            'delta' => ['required', 'array:ops'],
            'delta.ops' => ['required', 'array', 'list', 'max:5000'],
            'delta.ops.*' => ['required', 'array:insert,attributes'],
            'delta.ops.*.insert' => ['present', 'string', 'min:1', 'max:20000'],
            'delta.ops.*.attributes' => ['sometimes', 'array:bold,italic,underline,strike,blockquote,header,list,link'],
            'delta.ops.*.attributes.bold' => ['sometimes', 'boolean'],
            'delta.ops.*.attributes.italic' => ['sometimes', 'boolean'],
            'delta.ops.*.attributes.underline' => ['sometimes', 'boolean'],
            'delta.ops.*.attributes.strike' => ['sometimes', 'boolean'],
            'delta.ops.*.attributes.blockquote' => ['sometimes', 'boolean'],
            'delta.ops.*.attributes.header' => ['sometimes', 'integer', 'in:1,2,3'],
            'delta.ops.*.attributes.list' => ['sometimes', 'in:ordered,bullet'],
            'delta.ops.*.attributes.link' => ['sometimes', 'string', 'max:1000', 'url:http,https'],
        ];
        $result = [];
        foreach ($rules as $key => $value) {
            if (! str_starts_with($key, 'delta')) {
                continue;
            }
            if (! $required) {
                $value = array_map(fn (mixed $rule): mixed => $rule === 'required' ? 'sometimes' : $rule, $value);
            }
            $result[$prefix.substr($key, 5)] = $value;
        }

        return $result;
    }

    /** @param array{ops?:array} $delta */
    public static function text(array $delta): string
    {
        return implode('', array_column($delta['ops'] ?? [], 'insert'));
    }

    /** @param array{ops?:array} $delta @param array{email:string,campaignName:string,link:string} $context */
    public function html(array $delta, array $context): string
    {
        $lines = [];
        $parts = [];
        foreach ($delta['ops'] ?? [] as $op) {
            $chunks = explode("\n", $op['insert']);
            foreach ($chunks as $index => $chunk) {
                if ($chunk !== '') {
                    $parts[] = ['text' => $chunk, 'attributes' => $op['attributes'] ?? []];
                }
                if ($index < count($chunks) - 1) {
                    $lines[] = ['parts' => $parts, 'attributes' => $op['attributes'] ?? []];
                    $parts = [];
                }
            }
        }
        if ($parts !== []) {
            $lines[] = ['parts' => $parts, 'attributes' => []];
        }
        $output = '';
        $list = null;
        foreach ($lines as $line) {
            $attributes = $line['attributes'];
            $nextList = match ($attributes['list'] ?? null) {
                'ordered' => 'ol','bullet' => 'ul',default => null
            };
            if ($nextList !== $list) {
                if ($list) {
                    $output .= '</'.$list.'>';
                }
                if ($nextList) {
                    $output .= '<'.$nextList.'>';
                } $list = $nextList;
            }
            $tag = $list ? 'li' : (in_array($attributes['header'] ?? null, [1, 2, 3], true) ? 'h'.$attributes['header'] : (! empty($attributes['blockquote']) ? 'blockquote' : 'p'));
            $output .= '<'.$tag.'>'.($this->inline($line['parts'], $context) ?: '<br>').'</'.$tag.'>';
        }

        return $output.($list ? '</'.$list.'>' : '');
    }

    /** @param array<int,array{text:string,attributes:array}> $parts @param array<string,string> $context */
    private function inline(array $parts, array $context): string
    {
        $raw = implode('', array_column($parts, 'text'));
        $cursor = 0;
        $html = '';
        preg_match_all('/\{\{(email|campaign_name|tracking_link)\}\}/', $raw, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $index => $match) {
            $html .= $this->slice($parts, $cursor, $match[1]);
            $key = $matches[1][$index][0];
            $html .= $key === 'tracking_link' ? '<a href="'.e($context['link']).'" style="display:inline-block;padding:10px 18px;background:#7367f0;color:#fff;border-radius:5px;text-decoration:none">Lihat informasi</a>' : e($context[$key === 'email' ? 'email' : 'campaignName']);
            $cursor = $match[1] + strlen($match[0]);
        }

        return $html.$this->slice($parts, $cursor, strlen($raw));
    }

    /** @param array<int,array{text:string,attributes:array}> $parts */
    private function slice(array $parts, int $start, int $end): string
    {
        $offset = 0;
        $html = '';
        foreach ($parts as $part) {
            $left = max(0, $start - $offset);
            $right = min(strlen($part['text']), $end - $offset);
            $offset += strlen($part['text']);
            if ($right <= $left) {
                continue;
            }
            $value = e(substr($part['text'], $left, $right - $left));
            $attributes = $part['attributes'];
            foreach (['bold' => 'strong', 'italic' => 'em', 'underline' => 'u', 'strike' => 's'] as $key => $tag) {
                if (! empty($attributes[$key])) {
                    $value = '<'.$tag.'>'.$value.'</'.$tag.'>';
                }
            }
            if (isset($attributes['link']) && preg_match('/^https?:\/\//i', $attributes['link'])) {
                $value = '<a href="'.e($attributes['link']).'">'.$value.'</a>';
            }
            $html .= $value;
        }

        return $html;
    }
}
