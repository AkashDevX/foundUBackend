<?php

namespace App\Support;

use App\Models\TrainingPage;
use App\Models\TrainingPageSection;
use App\Models\TrainingSlideBlock;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class TrainingSlideBlocks
{
    /**
     * @return array{
     *     remove_ids: list<int>,
     *     photos: list<UploadedFile>,
     *     items: list<array{kind: string, label: ?string, body: ?string, file: ?UploadedFile}>,
     *     updates: array<int, array{label?: string, body?: string}>
     * }
     */
    public static function fromPayload(array $input, array $files = []): array
    {
        return self::incoming(Request::create('/', 'POST', $input, [], $files));
    }

    public static function incoming(Request $request): array
    {
        $removeIds = [];
        foreach ((array) $request->input('remove_blocks', []) as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $removeIds[] = (int) $id;
            }
        }

        $photos = [];
        foreach (self::fileList($request, 'photos') as $file) {
            $photos[] = self::requireFile($file, 'photo');
        }
        foreach (self::fileList($request, 'image') as $file) {
            $photos[] = self::requireFile($file, 'photo');
        }
        foreach (self::fileList($request, 'section_image') as $file) {
            $photos[] = self::requireFile($file, 'photo');
        }
        foreach (self::fileList($request, 'section_photos') as $file) {
            $photos[] = self::requireFile($file, 'photo');
        }

        $kinds = array_values((array) $request->input('new_kind', []));
        $labels = array_values((array) $request->input('new_label', []));
        $bodies = array_values((array) $request->input('new_body', []));
        $tokens = array_values((array) $request->input('new_token', []));
        $items = [];
        $photoAdds = count($photos);
        $otherAdds = 0;

        foreach ($kinds as $index => $kind) {
            if (! is_string($kind) || $kind === '') {
                continue;
            }
            $token = isset($tokens[$index]) ? preg_replace('/[^A-Za-z0-9_-]/', '', (string) $tokens[$index]) : '';
            $token = is_string($token) && $token !== '' ? $token : null;
            $validFile = self::fileAt($request, 'new_file', $index);

            if ($kind === 'photo') {
                if ($validFile === null) {
                    continue;
                }
                $photoAdds++;
                if ($photoAdds > 5) {
                    throw ValidationException::withMessages([
                        'photos' => 'You can upload up to 5 photos at a time.',
                    ]);
                }
                $items[] = [
                    'kind' => 'photo',
                    'label' => null,
                    'body' => null,
                    'file' => self::requireFile($validFile, 'photo'),
                    'token' => $token,
                ];

                continue;
            }

            if (! in_array($kind, TrainingSlideBlock::KINDS, true)) {
                throw ValidationException::withMessages([
                    'new_kind' => 'Choose text, PDF, video, link, instructions, or an important note.',
                ]);
            }

            $label = isset($labels[$index]) ? trim((string) $labels[$index]) : '';
            $body = isset($bodies[$index]) ? trim((string) $bodies[$index]) : '';

            if ($kind === 'link') {
                if ($body === '') {
                    $body = $label;
                    $label = '';
                }
                if ($body === '') {
                    continue;
                }
                $body = self::httpUrl($body);
                if ($label !== '' && self::sameAddress($label, $body)) {
                    $label = '';
                }
            } elseif ($kind === 'video' && $validFile === null) {
                $body = self::httpUrl($body);
            }

            if (in_array($kind, ['text', 'instruction', 'note'], true) && $body === '') {
                continue;
            }
            if ($kind === 'video' && $validFile === null && $body === '') {
                continue;
            }
            if ($kind === 'pdf' && $validFile === null) {
                if ($body === '' && $label === '') {
                    continue;
                }
                throw ValidationException::withMessages([
                    'new_file' => 'Choose a PDF file.',
                ]);
            }

            $storedFile = null;
            if ($validFile !== null && in_array($kind, ['pdf', 'video'], true)) {
                $storedFile = self::requireFile($validFile, $kind);
            }

            $items[] = [
                'kind' => $kind,
                'label' => $label !== '' ? mb_substr($label, 0, 200) : null,
                'body' => $body !== '' ? mb_substr($body, 0, $kind === 'link' ? 2000 : 8000) : null,
                'file' => $storedFile,
                'token' => $token,
            ];

            $otherAdds++;
            if ($otherAdds >= 12) {
                break;
            }
        }

        if (count($photos) > 5) {
            throw ValidationException::withMessages([
                'photos' => 'You can upload up to 5 photos at a time.',
            ]);
        }

        $updates = [];
        foreach ((array) $request->input('block_label', []) as $id => $label) {
            if (is_numeric($id)) {
                $updates[(int) $id]['label'] = mb_substr(trim((string) $label), 0, 200);
            }
        }
        foreach ((array) $request->input('block_body', []) as $id => $body) {
            if (is_numeric($id)) {
                $updates[(int) $id]['body'] = trim((string) $body);
            }
        }

        return [
            'remove_ids' => $removeIds,
            'photos' => $photos,
            'items' => $items,
            'updates' => $updates,
            'layout' => self::layoutInput($request->input('content_order')),
        ];
    }

    /**
     * @param  array{
     *     remove_ids: list<int>,
     *     photos: list<UploadedFile>,
     *     items: list<array{kind: string, label: ?string, body: ?string, file: ?UploadedFile}>,
     *     updates?: array<int, array{label?: string, body?: string}>
     * }  $incoming
     */
    public static function apply(TrainingPage|TrainingPageSection $owner, array $incoming): void
    {
        $connection = $owner->getConnectionName();
        $query = TrainingSlideBlock::on($connection);
        if ($owner instanceof TrainingPage) {
            $query->where('training_page_id', $owner->id)->whereNull('training_page_section_id');
        } else {
            $query->where('training_page_section_id', $owner->id);
        }

        $removeIds = $incoming['remove_ids'];
        foreach ($incoming['updates'] ?? [] as $id => $change) {
            if (in_array((int) $id, $removeIds, true) || ! is_array($change)) {
                continue;
            }
            $row = (clone $query)->where('id', (int) $id)->first();
            if ($row === null || $row->kind === 'photo') {
                continue;
            }
            $body = array_key_exists('body', $change) ? trim((string) $change['body']) : trim((string) $row->body);
            if ($row->kind === 'link' || ($row->kind === 'video' && $body !== '' && ! filled($row->file_path))) {
                $body = $body === '' ? '' : self::httpUrl($body);
            }
        }

        if ($incoming['remove_ids'] !== []) {
            $rows = (clone $query)->whereIn('id', $incoming['remove_ids'])->get();
            foreach ($rows as $row) {
                TrainingSlideMedia::delete($row->file_path);
                $row->delete();
            }
        }

        foreach ($incoming['updates'] ?? [] as $id => $change) {
            if (in_array((int) $id, $removeIds, true) || ! is_array($change)) {
                continue;
            }
            $row = (clone $query)->where('id', (int) $id)->first();
            if ($row === null || $row->kind === 'photo') {
                continue;
            }
            $label = array_key_exists('label', $change) ? trim((string) $change['label']) : (string) $row->label;
            $body = array_key_exists('body', $change) ? trim((string) $change['body']) : trim((string) $row->body);
            if ($row->kind === 'link' || ($row->kind === 'video' && $body !== '' && ! filled($row->file_path))) {
                $body = $body === '' ? '' : self::httpUrl($body);
            }
            $row->label = $label !== '' ? mb_substr($label, 0, 200) : null;
            $row->body = $body !== '' ? mb_substr($body, 0, $row->kind === 'link' ? 2000 : 8000) : null;
            $row->save();
        }

        $max = (clone $query)->max('sort_order');
        $next = is_numeric($max) ? ((int) $max) + 1 : 0;
        $created = [];

        foreach ($incoming['photos'] as $photo) {
            self::insert($owner, 'photo', null, null, TrainingSlideMedia::store($photo, 'blocks'), $next);
            $next++;
        }

        foreach ($incoming['items'] as $item) {
            $path = $item['file'] instanceof UploadedFile
                ? TrainingSlideMedia::store($item['file'], 'blocks')
                : null;
            $row = self::insert($owner, $item['kind'], $item['label'], $item['body'], $path, $next);
            if (is_string($item['token'] ?? null) && $item['token'] !== '') {
                $created[$item['token']] = (int) $row->id;
            }
            $next++;
        }

        $layout = $incoming['layout'] ?? null;
        if (! is_array($layout)) {
            return;
        }

        $blockIds = array_map(
            'intval',
            (clone $query)->orderBy('sort_order')->orderBy('id')->pluck('id')->all(),
        );
        $resolved = self::resolveLayoutTokens(
            $layout,
            $blockIds,
            $created,
            $owner instanceof TrainingPage,
            filled($owner->image_path),
        );
        $position = 0;
        foreach ($resolved as $token) {
            if (preg_match('/^block:(\d+)$/', $token, $match) !== 1) {
                continue;
            }
            TrainingSlideBlock::on($connection)->where('id', (int) $match[1])->update(['sort_order' => $position]);
            $position++;
        }
        $owner->content_order = $resolved;
        $owner->save();
    }

    /**
     * @param  iterable<int, TrainingSlideBlock>  $blocks
     * @return list<array{id: int, kind: string, label: ?string, body: ?string, has_file: bool, file_ext: ?string}>
     */
    public static function mobilePayload(iterable $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if (! in_array($block->kind, TrainingSlideBlock::KINDS, true)) {
                continue;
            }
            $out[] = [
                'id' => (int) $block->id,
                'kind' => $block->kind,
                'label' => is_string($block->label) && $block->label !== '' ? $block->label : null,
                'body' => is_string($block->body) && $block->body !== '' ? $block->body : null,
                'has_file' => is_string($block->file_path) && $block->file_path !== '',
                'file_ext' => is_string($block->file_path) && $block->file_path !== ''
                    ? (strtolower(pathinfo($block->file_path, PATHINFO_EXTENSION)) ?: null)
                    : null,
            ];
        }

        return $out;
    }

    public static function deleteOwnedFiles(TrainingPage|TrainingPageSection $owner): void
    {
        $owner->loadMissing('blocks');
        foreach ($owner->blocks as $block) {
            TrainingSlideMedia::delete($block->file_path);
        }
    }

    /**
     * @param  list<string>  $layout
     * @param  list<int>  $blockIds
     * @param  array<string, int>  $created
     * @return list<string>
     */
    public static function resolveLayoutTokens(
        array $layout,
        array $blockIds,
        array $created,
        bool $isPage,
        bool $keepImage,
    ): array {
        $allowed = array_fill_keys($blockIds, true);
        $out = [];
        $seen = [];

        foreach ($layout as $token) {
            if (! is_string($token)) {
                continue;
            }
            if (str_starts_with($token, 'new:')) {
                $key = substr($token, 4);
                if (! isset($created[$key])) {
                    continue;
                }
                $token = 'block:'.$created[$key];
            }
            if ($token === 'image') {
                if (! $keepImage || isset($seen['image'])) {
                    continue;
                }
                $out[] = 'image';
                $seen['image'] = true;

                continue;
            }
            if (in_array($token, ['title', 'body', 'bullets'], true)) {
                if (($token === 'title' || $token === 'bullets') && ! $isPage) {
                    continue;
                }
                if (isset($seen[$token])) {
                    continue;
                }
                $out[] = $token;
                $seen[$token] = true;

                continue;
            }
            if (preg_match('/^block:(\d+)$/', $token, $match) !== 1) {
                continue;
            }
            $id = (int) $match[1];
            if (! isset($allowed[$id]) || isset($seen[$token])) {
                continue;
            }
            $out[] = 'block:'.$id;
            $seen[$token] = true;
        }

        foreach (['title', 'body', 'bullets'] as $required) {
            if (($required === 'title' || $required === 'bullets') && ! $isPage) {
                continue;
            }
            if (! isset($seen[$required])) {
                $out[] = $required;
                $seen[$required] = true;
            }
        }
        if ($keepImage && ! isset($seen['image'])) {
            array_unshift($out, 'image');
            $seen['image'] = true;
        }
        foreach ($blockIds as $id) {
            $token = 'block:'.$id;
            if (isset($seen[$token])) {
                continue;
            }
            $out[] = $token;
            $seen[$token] = true;
        }

        return $out;
    }

    /**
     * @return list<array{token: string, block: ?TrainingSlideBlock}>
     */
    public static function editorFlow(TrainingPage|TrainingPageSection|null $owner, bool $isPage): array
    {
        $blocks = $owner?->blocks ?? collect();
        $byId = [];
        foreach ($blocks as $block) {
            $byId[(int) $block->id] = $block;
        }

        $saved = is_array($owner?->content_order) ? $owner->content_order : null;
        $tokens = [];
        if (is_array($saved) && $saved !== []) {
            foreach ($saved as $token) {
                if (is_string($token)) {
                    $tokens[] = $token;
                }
            }
        } else {
            if ($isPage && $owner && filled($owner->image_path)) {
                $tokens[] = 'image';
            }
            if ($isPage) {
                $tokens[] = 'title';
            }
            $tokens[] = 'body';
            if (! $isPage && $owner && filled($owner->image_path)) {
                $tokens[] = 'image';
            }
            if ($isPage) {
                $tokens[] = 'bullets';
            }
            foreach ($blocks as $block) {
                $tokens[] = 'block:'.$block->id;
            }
        }

        $items = [];
        $seen = [];
        foreach ($tokens as $token) {
            if ($token === 'image') {
                if (! $owner || ! filled($owner->image_path) || isset($seen['image'])) {
                    continue;
                }
                $items[] = ['token' => 'image', 'block' => null];
                $seen['image'] = true;

                continue;
            }
            if (in_array($token, ['title', 'body', 'bullets'], true)) {
                if (($token === 'title' || $token === 'bullets') && ! $isPage) {
                    continue;
                }
                if (isset($seen[$token])) {
                    continue;
                }
                $items[] = ['token' => $token, 'block' => null];
                $seen[$token] = true;

                continue;
            }
            if (preg_match('/^block:(\d+)$/', $token, $match) !== 1) {
                continue;
            }
            $id = (int) $match[1];
            if (! isset($byId[$id]) || isset($seen['block:'.$id])) {
                continue;
            }
            $items[] = ['token' => 'block:'.$id, 'block' => $byId[$id]];
            $seen['block:'.$id] = true;
        }

        foreach (['title', 'body', 'bullets'] as $required) {
            if (($required === 'title' || $required === 'bullets') && ! $isPage) {
                continue;
            }
            if (! isset($seen[$required])) {
                $items[] = ['token' => $required, 'block' => null];
                $seen[$required] = true;
            }
        }
        if ($owner && filled($owner->image_path) && ! isset($seen['image'])) {
            $items[] = ['token' => 'image', 'block' => null];
        }
        foreach ($blocks as $block) {
            $token = 'block:'.$block->id;
            if (isset($seen[$token])) {
                continue;
            }
            $items[] = ['token' => $token, 'block' => $block];
        }

        return $items;
    }

    /**
     * @return list<string>|null
     */
    private static function layoutInput(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($raw)) {
            return null;
        }

        $out = [];
        foreach ($raw as $token) {
            if (is_string($token) && preg_match('/^(title|body|bullets|image|block:\d+|new:[A-Za-z0-9_-]{1,40})$/', $token) === 1) {
                $out[] = $token;
            }
        }

        return $out;
    }

    private static function insert(
        TrainingPage|TrainingPageSection $owner,
        string $kind,
        ?string $label,
        ?string $body,
        ?string $path,
        int $sortOrder,
    ): TrainingSlideBlock {
        return TrainingSlideBlock::on($owner->getConnectionName())->create([
            'training_page_id' => $owner instanceof TrainingPage ? $owner->id : null,
            'training_page_section_id' => $owner instanceof TrainingPageSection ? $owner->id : null,
            'kind' => $kind,
            'label' => $label,
            'body' => $body,
            'file_path' => $path,
            'sort_order' => $sortOrder,
        ]);
    }

    private static function fileAt(Request $request, string $key, int $index): ?UploadedFile
    {
        $files = $request->file($key, []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        $file = is_array($files) ? ($files[$index] ?? null) : null;
        if (! $file instanceof UploadedFile) {
            return null;
        }
        if ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE) {
            throw ValidationException::withMessages([
                $key => 'That file is larger than the server allows.',
            ]);
        }
        if ($file->getError() === UPLOAD_ERR_NO_FILE || ! $file->isValid()) {
            return null;
        }

        return $file;
    }

    /**
     * @return list<UploadedFile>
     */
    private static function fileList(Request $request, string $key): array
    {
        $files = $request->file($key, []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        if (! is_array($files)) {
            return [];
        }

        $list = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE) {
                throw ValidationException::withMessages([
                    $key => 'That file is larger than the server allows.',
                ]);
            }
            if ($file->getError() === UPLOAD_ERR_NO_FILE || ! $file->isValid()) {
                continue;
            }
            $list[] = $file;
        }

        return array_values($list);
    }

    private static function requireFile(UploadedFile $file, string $kind): UploadedFile
    {
        $rules = match ($kind) {
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'video' => ['required', 'file', 'mimes:mp4,mov,webm,m4v', 'max:51200'],
            default => ['required', 'file'],
        };

        $validator = Validator::make(['file' => $file], ['file' => $rules]);
        if ($validator->fails()) {
            $message = match ($kind) {
                'photo' => 'Photos must be JPG, PNG, WEBP, or GIF and under 4 MB.',
                'pdf' => 'PDFs must be under 10 MB.',
                'video' => 'Videos must be MP4, MOV, or WEBM and under 50 MB.',
                default => 'That file could not be saved.',
            };
            throw ValidationException::withMessages(['file' => $message]);
        }

        return $file;
    }

    private static function httpUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages([
                'new_body' => 'Enter a link address.',
            ]);
        }

        return $value;
    }

    private static function sameAddress(string $label, string $url): bool
    {
        $label = rtrim(trim($label), '/');
        $url = rtrim(trim($url), '/');

        return strcasecmp($label, $url) === 0
            || strcasecmp('https://'.$label, $url) === 0
            || strcasecmp('http://'.$label, $url) === 0;
    }
}
