<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Core\HttpClient\HttpClient;

class GeminiService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private string $apiKey;
    private string $model;
    private int    $timeout;

    public function __construct(array $config, private readonly HttpClient $http)
    {
        $this->apiKey  = $config['api_key'];
        $this->model   = $config['model']   ?? 'gemini-2.5-flash';
        $this->timeout = (int) ($config['timeout'] ?? 180);
    }

    /**
     * Análise narrativa de campanhas Meta Ads com cruzamento de ROAS.
     */
    public function analyzeCampaigns(array $campaigns, int $days): string
    {
        if (empty($campaigns)) {
            return 'Nenhum dado de campanha disponível para análise.';
        }

        $url     = self::BASE_URL . '/' . $this->model . ':generateContent';
        $headers = ['x-goog-api-key' => $this->apiKey];
        $body    = [
            'contents' => [
                ['parts' => [['text' => $this->buildCampaignPrompt($campaigns, $days)]]]
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 8192,
                'thinkingConfig'  => ['thinkingBudget' => 0],
            ],
        ];

        $response = $this->http->post($url, $headers, $body, $this->timeout);

        return $response['candidates'][0]['content']['parts'][0]['text']
            ?? 'Análise indisponível. Verifique a configuração da API Gemini.';
    }

    /**
     * Retorna veredito JSON por campanha: {campaign_id, verdict, reasoning, action}.
     */
    public function analyzeRoasTrend(array $campaigns, int $days): array
    {
        if (empty($campaigns)) {
            return [];
        }

        $url     = self::BASE_URL . '/' . $this->model . ':generateContent';
        $headers = ['x-goog-api-key' => $this->apiKey];
        $body    = [
            'contents' => [
                ['parts' => [['text' => $this->buildTrendPrompt($campaigns, $days)]]]
            ],
            'generationConfig' => [
                'temperature'      => 0.2,
                'maxOutputTokens'  => 4096,
                'responseMimeType' => 'application/json',
                'thinkingConfig'   => ['thinkingBudget' => 0],
            ],
        ];

        $response = $this->http->post($url, $headers, $body, $this->timeout);
        $text     = $response['candidates'][0]['content']['parts'][0]['text'] ?? '[]';

        return json_decode($text, true) ?? [];
    }

    private function buildCampaignPrompt(array $campaigns, int $days): string
    {
        $rows = '';
        foreach ($campaigns as $c) {
            $roas = number_format((float) ($c['roas'] ?? 0), 2);
            $rows .= sprintf(
                "- %s | Gasto: $%.2f | Receita AV: $%.2f | ROAS: %s | Impressões: %s | Cliques: %s | CTR: %.2f%% | CPM: $%.2f\n",
                $c['campaign_name'] ?? 'N/A',
                $c['spend_usd']     ?? 0,
                $c['av_revenue_usd'] ?? 0,
                $roas,
                number_format((int) ($c['impressions'] ?? 0)),
                number_format((int) ($c['clicks'] ?? 0)),
                $c['ctr']  ?? 0,
                $c['cpm_usd'] ?? 0
            );
        }

        return <<<PROMPT
        Você é um especialista em marketing digital com foco em Meta Ads e monetização programática (AdSense/GAM).

        Analise os dados de performance das campanhas Meta Ads dos últimos {$days} dias abaixo e forneça:
        1. Um diagnóstico geral do portfolio de campanhas
        2. As 3 campanhas com melhor e pior ROAS, com reasoning
        3. Anomalias ou oportunidades identificadas (CTR, CPM, ROAS)
        4. Recomendações de ação prioritárias (em bullet points)

        Dados das campanhas:
        {$rows}

        ROAS = Receita ActiveView / Gasto Meta Ads. ROAS > 1 significa campanha lucrativa.
        Responda em português, com formatação Markdown.
        PROMPT;
    }

    public function generateBlogContent(
        string  $topic,
        string  $language,
        int     $wordCount,
        array   $buttons,
        bool    $includeHeaderButtons,
        bool    $includeTextBeforeButtons,
        array   $components = [],
        ?string $htmlTemplate = null
    ): string {
        $url     = self::BASE_URL . '/' . $this->model . ':generateContent';
        $headers = ['x-goog-api-key' => $this->apiKey];
        $body    = [
            'contents' => [
                ['parts' => [['text' => $this->buildBlogContentPrompt(
                    $topic, $language, $wordCount, $buttons,
                    $includeHeaderButtons, $includeTextBeforeButtons,
                    $components, $htmlTemplate
                )]]]
            ],
            'generationConfig' => [
                'temperature'     => 0.75,
                'maxOutputTokens' => 8192,
                'thinkingConfig'  => ['thinkingBudget' => 0],
            ],
        ];

        $response = $this->http->post($url, $headers, $body, $this->timeout);
        $html     = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        $html = preg_replace('/^```html?\s*/i', '', trim($html));
        $html = preg_replace('/\s*```\s*$/i', '', $html);
        $html = trim($html);

        if ($includeHeaderButtons && !empty($buttons)) {
            $buttonsBlock = $this->buildHeaderButtonsHtml($buttons);
            if (str_contains($html, '<!-- INSERT_BUTTONS -->')) {
                $html = str_replace('<!-- INSERT_BUTTONS -->', $buttonsBlock, $html);
            } else {
                $html = $buttonsBlock . "\n" . $html;
            }
        }

        $html = preg_replace_callback(
            '/<p((?:\s[^>]*)?)>((?:(?!<\/p>).)*)<\/p>/si',
            static function (array $m): string {
                $attrs   = $m[1];
                $content = trim($m[2]);

                if (preg_match('/\bstyle\s*=/i', $attrs)) {
                    return $m[0];
                }

                $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);

                if (count($sentences) <= 2) {
                    return $m[0];
                }

                return implode('', array_map(
                    static fn(array $chunk) => "<p{$attrs}>" . implode(' ', $chunk) . '</p>',
                    array_chunk($sentences, 2)
                ));
            },
            $html
        );

        return $html;
    }

    public function extractTemplateFromUrl(string $url): string
    {
        $userAgent = ['User-Agent' => 'Mozilla/5.0 (compatible; TemplateExtractor/1.0)'];
        $raw       = $this->http->getRaw($url, $userAgent, 30);
        $baseUrl   = preg_replace('/^(https?:\/\/[^\/]+).*$/i', '$1', $url);

        $cssBlocks = [];
        preg_match_all('/<style[^>]*>([\s\S]*?)<\/style>/i', $raw, $styleMatches);
        foreach ($styleMatches[1] as $block) {
            $cssBlocks[] = trim($block);
        }

        preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $raw, $linkMatches);
        $totalCssBytes = array_sum(array_map('strlen', $cssBlocks));
        foreach ($linkMatches[1] as $href) {
            if ($totalCssBytes >= 61440) { break; }
            if (str_starts_with($href, '//'))        { $href = 'https:' . $href; }
            elseif (str_starts_with($href, '/'))     { $href = $baseUrl . $href; }
            elseif (!str_starts_with($href, 'http')) { continue; }
            try {
                $sheet = $this->http->getRaw($href, $userAgent, 15);
                if (strlen($sheet) > 0 && !str_contains(substr($sheet, 0, 10), '<')) {
                    $remaining   = 61440 - $totalCssBytes;
                    $cssBlocks[] = substr($sheet, 0, $remaining);
                    $totalCssBytes += min(strlen($sheet), $remaining);
                }
            } catch (\Throwable) {}
        }

        $cssSection = implode("\n\n", array_filter($cssBlocks));

        $html = '';
        if (preg_match('/<article[^>]*>([\s\S]*?)<\/article>/i', $raw, $m)) {
            $html = $m[1];
        } elseif (preg_match('/<main[^>]*>([\s\S]*?)<\/main>/i', $raw, $m)) {
            $html = $m[1];
        } elseif (preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $raw, $m)) {
            $html = $m[1];
        } else {
            $html = $raw;
        }

        $html = preg_replace('/<script[\s\S]*?<\/script>/i',     '', $html);
        $html = preg_replace('/<style[\s\S]*?<\/style>/i',       '', $html);
        $html = preg_replace('/<noscript[\s\S]*?<\/noscript>/i', '', $html);
        $html = preg_replace('/<iframe[\s\S]*?<\/iframe>/i',     '', $html);
        $html = preg_replace('/<(nav|header|footer)[\s\S]*?<\/\1>/i', '', $html);
        $html = trim((string) $html);

        $cssInstruction = $cssSection !== ''
            ? "CSS RULES (from the page's stylesheets — use these to resolve class-based styles):\n<style>\n{$cssSection}\n</style>\n\n"
            : '';

        $prompt = <<<PROMPT
You are an HTML template extractor and CSS inliner. Below you have the article HTML of a real blog post, plus the page's CSS rules.

YOUR TASKS — follow every rule exactly:
1. Read the CSS rules and identify every rule that applies to any element in the article HTML.
2. For each element, compute its final visual styles (color, background, font-size, font-weight, padding, margin, border, border-radius, display, width, etc.) and write them as an inline `style="..."` attribute.
3. After inlining, REMOVE all `class` and `id` attributes — the output must not depend on any external stylesheet.
4. Remove any remaining ad containers, tracking pixels, share buttons, comment sections, author bios, related-post widgets, and breadcrumbs.
5. Keep ALL article structure intact: headings, paragraphs, CTA buttons, tip/info boxes, warning boxes, accordions, images, lists, and any other visual component.
6. Do NOT change, add, or remove any visible text — every heading and paragraph must remain exactly as-is.
7. Return ONLY the cleaned, fully self-contained article HTML. No <!doctype>, no <html>, no <head>, no <body> tags. No markdown, no code fences, no explanation.

{$cssInstruction}ARTICLE HTML:
{$html}
PROMPT;

        $apiUrl  = self::BASE_URL . '/' . $this->model . ':generateContent';
        $headers = ['x-goog-api-key' => $this->apiKey];
        $body    = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature'     => 0.1,
                'maxOutputTokens' => 16384,
                'thinkingConfig'  => ['thinkingBudget' => 0],
            ],
        ];

        $response = $this->http->post($apiUrl, $headers, $body, 120);
        $result   = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        $result = preg_replace('/^```html?\s*/i', '', trim($result));
        $result = preg_replace('/\s*```\s*$/i', '', $result);

        return trim($result) ?: $html;
    }

    private function buildHeaderButtonsHtml(array $buttons): string
    {
        $colors = ['#16a34a', '#15803d', '#166534'];
        $links  = '';
        foreach (array_slice($buttons, 0, 3) as $i => $btn) {
            $label  = htmlspecialchars($btn['label'] ?? 'Visit Website', ENT_QUOTES, 'UTF-8');
            $href   = htmlspecialchars($btn['href']  ?? '#',              ENT_QUOTES, 'UTF-8');
            $color  = $colors[$i] ?? '#16a34a';
            $links .= '<a href="' . $href . '" style="display:block;width:80%;background:' . $color
                . ';color:#fff;padding:12px 0;border-radius:8px;font-size:14px;font-weight:700;'
                . 'text-decoration:none;text-align:center;">' . $label . "</a>\n";
        }
        return '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;margin:0 0 28px;">'
            . "\n" . $links . '</div>';
    }

    private function buildBlogContentPrompt(
        string  $topic,
        string  $language,
        int     $wordCount,
        array   $buttons,
        bool    $includeHeaderButtons,
        bool    $includeTextBeforeButtons,
        array   $components = [],
        ?string $htmlTemplate = null
    ): string {
        $btnLines = '';
        foreach ($buttons as $i => $btn) {
            $label     = htmlspecialchars_decode($btn['label'] ?? ('Button ' . ($i + 1)));
            $href      = $btn['href'] ?? '';
            $btnLines .= "  Button " . ($i + 1) . ": label=\"{$label}\" url=\"{$href}\"\n";
        }
        if (empty($btnLines)) {
            $btnLines = "  (no buttons provided — omit CTA elements)\n";
        }

        $headerOpt         = $includeHeaderButtons ? 'YES' : 'NO';
        $textOpt           = $includeTextBeforeButtons ? 'YES' : 'NO';
        $componentsSection = $this->buildComponentsSection($components);

        if ($htmlTemplate !== null && trim($htmlTemplate) !== '') {
            return $this->buildTemplateBasedPrompt(
                $topic, $language, $wordCount, $buttons, $htmlTemplate, $components
            );
        }

        return <<<PROMPT
You are an expert blog post writer. Write a complete, high-quality blog post as HTML.

REQUIREMENTS:
- Topic: {$topic}
- Language: Write ALL visible text in {$language}. Title, headings, paragraphs, labels, everything.
- Target word count: approximately {$wordCount} words of readable content
- CTA buttons — use ONLY these URLs (never invent URLs):
{$btnLines}
- Include header card + 3 buttons at top of article: {$headerOpt}
- Include introductory paragraphs before each CTA button: {$textOpt}

OUTPUT RULES:
1. Return ONLY the HTML body content. No <!doctype>, no <html>, no <head>, no <body> tags.
2. No markdown, no explanation, no ```html fences — raw HTML only.
3. Use inline CSS for all elements — no CSS framework classes anywhere.
4. Distribute content to reach the target word count.
5. Each <p> tag must contain at most 2 lines of readable text — break longer content into multiple <p> tags.
6. ALL button-like or link elements MUST use <a href="..."> tags. NEVER use <button> tags anywhere in the output.

HTML ELEMENT FORMATS (use exactly these patterns):

CTA button block (use the SAME button for both occurrences — same URL and label):
<div style="margin:28px 0 6px;">
  <a href="URL" style="display:block;width:80%;margin:0 auto;background:#16a34a;color:#fff;padding:14px 0;border-radius:8px;font-size:15px;font-weight:700;text-decoration:none;letter-spacing:.2px;text-align:center;">LABEL</a>
</div>
<p style="text-align:center;font-size:12px;color:#9ca3af;margin:0 0 24px;">Official website — results may vary</p>

Dark CTA block (use once, after the FAQ section):
<div style="background:#14532d;color:#fff;border-radius:12px;padding:32px 28px;margin:36px 0;text-align:center;">
  <h3 style="margin:0 0 8px;font-size:1.3em;color:#fff;">HEADING</h3>
  <p style="margin:0 0 20px;color:#bbf7d0;font-size:15px;">SUBTITLE</p>
  <a href="URL" style="display:block;width:80%;margin:0 auto;background:#fff;color:#14532d;padding:14px 0;border-radius:8px;font-size:15px;font-weight:700;text-decoration:none;text-align:center;">BUTTON LABEL</a>
  <p style="margin:10px 0 0;font-size:12px;color:#86efac;">FINE PRINT</p>
</div>

Info tip box:
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 20px;margin:24px 0;">
  <p style="margin:0;"><strong>✓ Tip:</strong> TEXT</p>
</div>

Warning box:
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:16px 20px;margin:24px 0;">
  <p style="margin:0;"><strong>⚠ Note:</strong> TEXT</p>
</div>

Header card (only if include_header_buttons=YES):
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:24px 28px;margin:0 0 20px;">
  <span style="background:#16a34a;color:#fff;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px;">BADGE</span>
  <h2 style="margin:12px 0 8px;font-size:1.35em;">CARD TITLE</h2>
  <p style="margin:0;color:#374151;font-size:15px;">CARD DESCRIPTION</p>
</div>

Header buttons (only if include_header_buttons=YES):
After the header card div, insert this exact HTML comment on its own line — do NOT generate button tags here:
<!-- INSERT_BUTTONS -->

BUTTON RULES — read carefully:
- When include_header_buttons=YES: the element immediately after <!-- INSERT_BUTTONS --> MUST be a <p> paragraph of body text.
- Do NOT place any CTA button block within the first 10 body paragraphs.
- The CTA button block uses ONE button with ONE URL. Never output 3 separate CTA button blocks in a row.
- Use the CTA button block exactly TWICE in the article body: once after at least 10 body paragraphs, and once as the very last element before the disclaimer.
- Both body CTA occurrences must use the same URL and label (pick Button 1).
- Maximum 6 CTAs in the whole document: 3 header buttons + 2 body CTA blocks + 1 dark CTA block = 6.
{$componentsSection}
ARTICLE STRUCTURE (follow this order):
1. {IF include_header_buttons=YES} Header card → INSERT_BUTTONS → 2-3 MANDATORY intro paragraphs {ENDIF}
2. {IF include_header_buttons=NO AND include_text_before_buttons=YES} 2-3 intro paragraphs {ENDIF}
3. <hr style="border:none;border-top:2px solid #e5e7eb;margin:36px 0;">
4. <h2>What Is [topic]?</h2> + 3 paragraphs
5. <h2>How It Works</h2> + ordered list (4-5 steps) + 1-2 explanatory paragraphs
6. <h2>Key Benefits</h2> + unordered list (5-7 items with <strong> titles and descriptions) + 1 paragraph
7. Info tip box
8. <h2>Main Features</h2> + 3-4 feature paragraphs
9. Warning box
10. <h2>Frequently Asked Questions</h2> + 6-8 Q&A pairs as <h3>Question?</h3><p>Answer.</p>
   [At this point at least 10 body paragraphs have been written — place the first CTA button block here]
11. CTA button block (first occurrence — after 10+ paragraphs)
12. Dark CTA block (use the same URL as the CTA button above)
13. CTA button block (second and final occurrence — exact same button as #11, replicated)
14. <p style="font-size:12px;color:#9ca3af;margin:32px 0 0;">Disclaimer line. Always verify on official website.</p>
PROMPT;
    }

    private function buildComponentsSection(array $components): string
    {
        if (empty($components)) {
            return '';
        }

        $btnColors = [
            'primary'   => ['bg' => '#16a34a', 'text' => '#ffffff'],
            'secondary' => ['bg' => '#7c3aed', 'text' => '#ffffff'],
            'accent'    => ['bg' => '#0891b2', 'text' => '#ffffff'],
            'neutral'   => ['bg' => '#374151', 'text' => '#ffffff'],
            'info'      => ['bg' => '#2563eb', 'text' => '#ffffff'],
            'success'   => ['bg' => '#16a34a', 'text' => '#ffffff'],
            'warning'   => ['bg' => '#d97706', 'text' => '#000000'],
            'error'     => ['bg' => '#dc2626', 'text' => '#ffffff'],
        ];

        $cardColors = [
            'base-100'  => ['bg' => '#ffffff',  'border' => '#e5e7eb'],
            'base-200'  => ['bg' => '#f3f4f6',  'border' => '#d1d5db'],
            'primary'   => ['bg' => '#f0fdf4',  'border' => '#86efac'],
            'secondary' => ['bg' => '#f5f3ff',  'border' => '#c4b5fd'],
            'accent'    => ['bg' => '#ecfeff',  'border' => '#a5f3fc'],
            'neutral'   => ['bg' => '#f1f5f9',  'border' => '#cbd5e1'],
            'info'      => ['bg' => '#eff6ff',  'border' => '#bfdbfe'],
            'success'   => ['bg' => '#f0fdf4',  'border' => '#86efac'],
            'warning'   => ['bg' => '#fffbeb',  'border' => '#fde68a'],
            'error'     => ['bg' => '#fef2f2',  'border' => '#fecaca'],
        ];

        $lines = [
            '',
            'CUSTOM COMPONENTS — include these blocks in your article body, distributed throughout the content in the order listed:',
            'NEVER use <button> tags — all interactive elements must be <a href="..."> tags.',
            'Use ONLY inline CSS — no class names from any CSS framework.',
            '',
        ];

        foreach ($components as $i => $comp) {
            $type  = $comp['type']     ?? '';
            $qty   = max(1, (int) ($comp['quantity'] ?? 1));
            $color = preg_replace('/[^a-z0-9\-]/', '', $comp['color'] ?? 'base-200');
            $pos   = $i + 1;

            switch ($type) {
                case 'accordion':
                    $lines[] = "{$pos}. ACCORDION — include {$qty} instance(s):";
                    $lines[] = '   Repeat this <details> block ' . $qty . ' time(s) with unique question and answer each time:';
                    $lines[] = '   <details style="border:1px solid #e5e7eb;border-radius:12px;margin-bottom:10px;overflow:hidden;">';
                    $lines[] = '     <summary style="padding:14px 16px;font-size:13px;font-weight:700;cursor:pointer;background:#f9fafb;">Unique question or heading</summary>';
                    $lines[] = '     <div style="padding:12px 16px 14px;font-size:13px;line-height:1.7;color:#4b5563;border-top:1px solid #e5e7eb;">Answer. Max 2 lines.</div>';
                    $lines[] = '   </details>';
                    break;

                case 'buttons':
                    $c = $btnColors[$color] ?? $btnColors['primary'];
                    $lines[] = "{$pos}. COLORED BUTTONS — include {$qty} button(s) in one flex group:";
                    $lines[] = '   ALL buttons must be <a href> tags using CTA URLs. Wrap in one container:';
                    $lines[] = '   <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin:24px 0;">';
                    for ($b = 0; $b < $qty; $b++) {
                        $lines[] = '     <a href="USE_ONE_CTA_URL" style="display:inline-flex;align-items:center;justify-content:center;padding:10px 24px;background:' . $c['bg'] . ';color:' . $c['text'] . ';border-radius:8px;font-weight:600;font-size:14px;text-decoration:none;">Relevant Label</a>';
                    }
                    $lines[] = '   </div>';
                    break;

                case 'card':
                    $c = $cardColors[$color] ?? $cardColors['base-100'];
                    $lines[] = "{$pos}. CARD WITH CENTERED CONTENT — include {$qty} instance(s):";
                    $lines[] = '   Repeat this card ' . $qty . ' time(s) with unique content:';
                    $lines[] = '   <div style="background:' . $c['bg'] . ';border:1px solid ' . $c['border'] . ';border-radius:12px;padding:24px;margin:24px 0;text-align:center;box-shadow:0 4px 6px -1px rgba(0,0,0,.1);">';
                    $lines[] = '     <h3 style="margin:0 0 8px;font-size:16px;font-weight:700;">Unique Card Title</h3>';
                    $lines[] = '     <p style="margin:0 0 16px;font-size:14px;color:#6b7280;line-height:1.6;">Short description. Max 2 lines.</p>';
                    $lines[] = '     <a href="USE_ONE_CTA_URL" style="display:inline-flex;align-items:center;padding:8px 20px;background:#16a34a;color:#fff;border-radius:8px;font-weight:600;font-size:13px;text-decoration:none;">Action Label</a>';
                    $lines[] = '   </div>';
                    break;
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function buildTemplateBasedPrompt(
        string $topic,
        string $language,
        int    $wordCount,
        array  $buttons,
        string $htmlTemplate,
        array  $components = []
    ): string {
        $btnLines = '';
        foreach ($buttons as $i => $btn) {
            $label     = htmlspecialchars_decode($btn['label'] ?? ('Button ' . ($i + 1)));
            $href      = $btn['href'] ?? '';
            $btnLines .= "  Button " . ($i + 1) . ": label=\"{$label}\" url=\"{$href}\"\n";
        }
        if (empty($btnLines)) {
            $btnLines = "  (no buttons — omit CTA elements)\n";
        }

        $componentsSection = $this->buildComponentsSection($components);

        return <<<PROMPT
You are an expert conversion copywriter and front-end developer. You write persuasive long-form content using the AIDA framework (Attention → Interest → Desire → Action).

The user has provided an HTML template below. Your job is to:
1. Analyze the template's structure, visual components, layout, colors, and inline CSS styles.
2. Reproduce that exact same HTML structure and styling.
3. Replace ALL text content with brand-new content about the specified topic — written in AIDA order.
4. Do NOT copy any original text — every visible sentence must be rewritten for the new topic.
5. Keep every HTML element, inline style, color, spacing, and component from the template.
6. CTA buttons must use ONLY the URLs listed below — never invent URLs.
7. Output each visual section as a SEPARATE top-level element — do NOT wrap everything in a single container div.

CONTENT REQUIREMENTS:
- Topic: {$topic}
- Language: Write ALL visible text in {$language}. Title, headings, paragraphs, labels — everything.
- CTA buttons — use ONLY these URLs:
{$btnLines}
AIDA WRITING FRAMEWORK — apply across the full article:
- ATTENTION (beginning): Open with a bold, provocative headline and 2–3 short punchy paragraphs that immediately hook the reader with a relatable pain point or surprising fact.
- INTEREST (early middle): Explain the problem in depth, present evidence, tell a short story or scenario the reader recognizes.
- DESIRE (middle–late): Show the solution in action, highlight concrete benefits, use social proof language, contrast the reader's current situation with the improved future state.
- ACTION (end): Close with urgency and a clear, specific call-to-action. Every CTA section must include benefit-driven copy.

ARTICLE STRUCTURE — MANDATORY (beginning → middle → end):
1. BEGINNING — Attention hook: headline + problem hook paragraphs + first CTA if template has one at the top.
2. MIDDLE — Interest + Desire: deep content sections, benefits, evidence, comparisons, FAQs, components.
3. END — Action close: final reinforcement paragraph + last CTA + disclaimer.

WORD COUNT REQUIREMENT — MANDATORY:
- Target: {$wordCount} words of visible text.
- After filling the template, count your words. If below {$wordCount}: expand existing paragraphs, add new AIDA-aligned subsections.
- Every expansion must follow the same inline CSS style as the surrounding template.

OUTPUT RULES:
1. Return ONLY the HTML body content. No <!doctype>, no <html>, no <head>, no <body> tags.
2. No markdown, no explanation, no ```html fences — raw HTML only.
3. Preserve all inline CSS from the template exactly as-is.
4. Where the template uses a placeholder marker <!-- INSERT_BUTTONS -->, keep it — it will be replaced server-side.
{$componentsSection}
HTML TEMPLATE TO FOLLOW:
{$htmlTemplate}
PROMPT;
    }

    private function buildTrendPrompt(array $campaigns, int $days): string
    {
        $json = json_encode(array_map(fn($c) => [
            'campaign_id'    => $c['campaign_id']    ?? '',
            'campaign_name'  => $c['campaign_name']  ?? '',
            'roas'           => round((float) ($c['roas'] ?? 0), 4),
            'spend_usd'      => round((float) ($c['spend_usd'] ?? 0), 2),
            'av_revenue_usd' => round((float) ($c['av_revenue_usd'] ?? 0), 2),
            'impressions'    => (int) ($c['impressions'] ?? 0),
            'ctr'            => round((float) ($c['ctr'] ?? 0), 3),
        ], $campaigns), JSON_PRETTY_PRINT);

        return <<<PROMPT
        Analise as campanhas Meta Ads dos últimos {$days} dias e retorne um array JSON.
        Cada elemento deve ter: campaign_id, verdict (SCALE/MAINTAIN/PAUSE/INVESTIGATE), reasoning (1 frase), action (ação concreta).
        ROAS > 1.5 = SCALE, 0.8–1.5 = MAINTAIN, < 0.8 = PAUSE ou INVESTIGATE.

        Dados:
        {$json}

        Retorne APENAS o array JSON, sem markdown.
        PROMPT;
    }
}
