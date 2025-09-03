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
 */
class process_convert_text_to_speech extends \aiprovider_openai\abstract_processor {
    /**
     * Define the settings for the text-to-speech action.
     * This includes the endpoint, model, voice, and format.
     */
    public function get_request_definition(): array {
        return [
            'input' => [
                'type' => 'text',
                'label' => get_string('field_input', 'aiprovider_openai_extension'),
                'required' => true,
            ],
            'model' => [
                'type' => 'text',
                'label' => get_string('field_model', 'aiprovider_openai_extension'),
                'required' => false,
            ],
            'voice' => [
                'type' => 'text',
                'label' => get_string('field_voice', 'aiprovider_openai_extension'),
                'required' => false,
            ],
            'instructions' => [
                'type' => 'text',
                'label' => get_string('field_instructions', 'aiprovider_openai_extension'),
                'required' => false,
            ],
            'response_format' => [
                'type' => 'text',
                'label' => get_string('field_response_format', 'aiprovider_openai_extension'),
                'required' => false,
            ],
            'speed' => [
                'type' => 'text',
                'label' => get_string('field_response_speed', 'aiprovider_openai_extension'),
                'required' => false,
            ],
            'stream_format' => [
                'type' => 'text',
                'label' => get_string('field_response_stream_format', 'aiprovider_openai_extension'),
                'required' => false,
            ],
        ];
    }

    /**
     * Create the request object to send to the OpenAI API.
     * @param string $userid
     */
    protected function create_request_object(string $userid): RequestInterface {
        $url = $this->get_endpoint();

        $model = $this->get_model();
        $voice = $this->action->get_configuration('voice') ?? $this->get_default('voice');
        $format = $this->action->get_configuration('format') ?? $this->get_default('format');

        $payload = [
            'model' => $model,
            'voice' => $voice,
            'input' => (string)$this->action->get_configuration('texttoread'),
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

    /**
     * Parse and persist a successful audio response.
     * Saves a file and returns structured payload used by prepare_response().
     */
    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $content = (string)$response->getBody();

        // Determine mimetype: trust header first, then guess from requested format.
        $headerctype = $response->getHeaderLine('Content-Type');
        $format = $this->action->get_configuration('format') ?? 'mp3';
        $mimetype = $headerctype !== '' ? $headerctype : $this->guess_mimetype($format);

        $filename = 'openai-tts-' . time() . '.' . $this->extension_from_mimetype_or_format($mimetype, $format);

        // Use the action context if available; fallback to system.
        $context = method_exists($this->action, 'get_contextid') && $this->action->get_contextid()
            ? \context::instance_by_id($this->action->get_contextid())
            : \context_system::instance();

        $fs = get_file_storage();
        $file = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'aiprovider_openai_extension',
            'filearea'  => 'generatedaudio',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        ], $content);

        return [
            'success'  => true,
            'mimetype' => $mimetype,
            'filename' => $filename,
            'filesize' => $file->get_filesize(),
            'fileid'   => $file->get_id(),
        ];
    }

    /**
     * Generic parser that delegates to success/error paths.
     * Kept for compatibility if the abstract expects parse_response().
     */
    protected function parse_response(ResponseInterface $response): array {
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $this->handle_api_error($response);
        }
        return $this->handle_api_success($response);
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
            'audio/mp3'     => 'mp3',
            'audio/opus'     => 'opus',
            'audio/aac'      => 'aac',
            'audio/flac'     => 'flac',
            'audio/wav'      => 'wav',
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
     * Get the default value for a specific configuration key.
     *
     * @param string $key The configuration key.
     * @return ?string The default value for the configuration key.
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
