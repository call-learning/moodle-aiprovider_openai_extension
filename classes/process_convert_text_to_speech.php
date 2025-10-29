<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace aiprovider_openai_extension;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class to process text-to-speech requests using OpenAI's API.
 * This class extends the abstract processor to handle
 * the specific requirements for generating audio from text.
 *
 * @package   aiprovider_openai_extension
 * @copyright   2025 Laurent David <laurent@call-learning.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_convert_text_to_speech extends \aiprovider_openai\abstract_processor {
    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $url = $this->get_endpoint();

        $model = $this->get_model();
        $voice = $this->action->get_configuration('voice') ?? $this->get_default('voice');
        $format = $this->action->get_configuration('format') ?? $this->get_default('format');

        $payload = [
            'model' => $model,
            'voice' => $voice,
            'input' => (string) $this->action->get_configuration('texttoread'),
            'format' => $format, // OpenAI accepte 'mp3', 'wav', 'flac', 'ogg' (selon version API).
        ];
        return new Request(
            method: 'POST',
            uri: $url,
            headers: [
                'Content-Type' => 'application/json',
            ],
            body: json_encode($payload),
        );
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $content = (string) $response->getBody();

        // Determine mimetype: trust header first, then guess from requested format.
        $headerctype = $response->getHeaderLine('Content-Type');
        $format = $this->action->get_configuration('format') ?? 'mp3';
        $mimetype = $headerctype !== '' ? $headerctype : $this->guess_mimetype($format);
        $currenttime = \core\di::get(\core\clock::class)->time();
        $randompart = $currenttime . bin2hex(random_bytes(5));
        $filename = 'openai-tts-' . $randompart . '.' . $this->extension_from_mimetype_or_format($mimetype, $format);

        // Use the action context if available; fallback to system.
        $context = method_exists($this->action, 'get_contextid') && $this->action->get_contextid()
            ? \context::instance_by_id($this->action->get_contextid())
            : \context_system::instance();

        $fs = get_file_storage();
        $file = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'aiprovider_openai_extension',
            'filearea' => 'generatedaudio',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);

        return [
            'success' => true,
            'mimetype' => $mimetype,
            'filename' => $filename,
            'filesize' => $file->get_filesize(),
            'fileid' => $file->get_id(),
        ];
    }

    #[\Override]
    public function get_action_name(): string {
        return 'convert_text_to_speech';
    }

    /**
     * Guess the MIME type based on the format.
     */
    private function guess_mimetype(string $format): string {
        return match (strtolower($format)) {
            'mp3' => 'audio/mp3',
            'opus' => 'audio/opus',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'wav' => 'audio/wav',
            'pcm' => 'audio/vnd.wave',
            default => 'application/octet-stream',
        };
    }

    /**
     * Pick an extension from mimetype or fallback to provided format.
     */
    private function extension_from_mimetype_or_format(string $mimetype, string $fallbackformat): string {
        $map = [
            'audio/mp3' => 'mp3',
            'audio/opus' => 'opus',
            'audio/aac' => 'aac',
            'audio/flac' => 'flac',
            'audio/wav' => 'wav',
            'audio/vnd.wave' => 'wav',
        ];
        return $map[strtolower($mimetype)] ?? strtolower($fallbackformat);
    }

    #[\Override]
    protected function get_endpoint(): UriInterface {
        return new Uri(get_config('aiprovider_openai_extension', 'action_convert_text_to_speech_endpoint'));
    }

    #[\Override]
    protected function get_model(): string {
        return get_config('aiprovider_openai_extension', 'action_convert_text_to_speech_model');
    }

    /**
     * Get default values
     *
     * @param string $key
     * @return string|null
     * @throws \dml_exception
     */
    protected function get_default(string $key): ?string {
        if ($key === 'voice') {
            return get_config('aiprovider_openai_extension', 'action_convert_text_to_speech_voice');
        }
        if ($key === 'format') {
            return get_config('aiprovider_openai_extension', 'action_convert_text_to_speech_format');
        }
        return null;
    }
}
