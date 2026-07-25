<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TermsAndConditions;
use App\Support\DisplayTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (pre-login) terms for the mobile create-account modal.
 * Requires tenant resolution via X-Company-Slug — each org has its own copy.
 */
class TermsAndConditionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $company = $request->tenantCompany();
        if ($company === null) {
            return response()->json(['message' => 'Tenant not specified.'], 422);
        }

        $terms = TermsAndConditions::query()->orderBy('id')->first();

        $lastUpdated = null;
        if ($terms?->last_updated_on) {
            $lastUpdated = DisplayTimezone::format($terms->last_updated_on, 'j F Y');
        }

        $content = trim((string) ($terms?->content ?? ''));
        $sections = $this->sectionsFromContent($content);

        return response()->json([
            'company' => [
                'slug' => $company->slug,
                'name' => $company->name,
            ],
            'last_updated' => $lastUpdated,
            'content' => $content,
            'sections' => $sections,
        ]);
    }

    /**
     * Split admin textarea into titled sections when possible.
     * Expects blocks like "1. Title\n\nBody…" separated by blank lines between sections.
     *
     * @return list<array{title: string, body: string}>
     */
    private function sectionsFromContent(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $blocks = preg_split("/\n{2,}/", $content) ?: [];
        $sections = [];
        $pendingTitle = null;
        $pendingBody = [];

        $flush = static function () use (&$sections, &$pendingTitle, &$pendingBody): void {
            if ($pendingTitle === null && $pendingBody === []) {
                return;
            }
            $sections[] = [
                'title' => $pendingTitle ?? 'Terms',
                'body' => trim(implode("\n\n", $pendingBody)),
            ];
            $pendingTitle = null;
            $pendingBody = [];
        };

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            if (preg_match('/^(\d+\.\s+.+)$/u', $block, $m) && ! str_contains($block, "\n")) {
                $flush();
                $pendingTitle = $m[1];
                continue;
            }

            if (preg_match('/^(\d+\.\s+[^\n]+)\n+([\s\S]+)$/u', $block, $m)) {
                $flush();
                $pendingTitle = trim($m[1]);
                $pendingBody[] = trim($m[2]);
                continue;
            }

            $pendingBody[] = $block;
        }

        $flush();

        if ($sections === []) {
            return [['title' => 'Terms and Conditions', 'body' => $content]];
        }

        return $sections;
    }
}
