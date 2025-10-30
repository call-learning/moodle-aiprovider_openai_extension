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

use GuzzleHttp\Psr7\MultipartStream;
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
 * @package aiprovider_openai_extension
 * @copyright 2025 Laurent David <laurent@call-learning.fr>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_convert_speech_to_text extends \aiprovider_openai\abstract_processor {
    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $url = $this->get_endpoint();

        $model = $this->get_model();
        $language = $this->action->get_configuration('language') ?? $this->get_default('language');

        // We use the basic setting for the payload (JSON and verbose so we get the stats).
        $payload = [
            'model' => $model,
            'language' => $language,
            'response_format' => 'verbose_json', // We need the start/end of each words.
            'timestamp_granularities' => ['word', 'segment'], // Get timestamps for words and probabilty for segments.
        ];
        // Build the multipart array.
        $multipart = [];

        // Add form fields.
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $multipart[] = [
                        'name' => $key . '[]',
                        'contents' => $item,
                    ];
                }
            } else {
                $multipart[] = [
                    'name' => $key,
                    'contents' => $value,
                ];
            }
        }

        // Add the file.
        $audiofile = $this->action->get_configuration('audiofile');
        $multipart[] = [
            'name' => $audiofile->get_filename() ? 'file' : 'filedata',
            'contents' => $audiofile->get_content(),
            'filename' => $audiofile->get_filename(),
        ];

        // Create the request.
        return new Request(
            'POST',
            $url,
            [],
            new MultipartStream($multipart)
        );
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $responsebody = $response->getBody();
        $response = json_decode($responsebody->getContents(), true);
        $wordstats = [];
        if ($response['words'] && is_array($response['words'])) {
            // Make sure segments are objects with required properties.
            $wordstats = array_map(
                fn($segment) => [
                    'text' => trim($segment['word'] ?? ''),
                    'start' => floatval($segment['start'] ?? 0.0),
                    'end' => floatval($segment['end'] ?? 0.0),
                    'confidence' => 0, // To be filled later.
                ],
                $response['words']
            );
        }

        if ($response['segments'] && is_array($response['segments'])) {
            // Make sure segments are objects with required properties.
            $segments = array_map(
                fn($segment) => [
                    'text' => trim($segment['text'] ?? ''),
                    'start' => floatval($segment['start'] ?? 0.0),
                    'end' => floatval($segment['end'] ?? 0.0),
                    'confidence' => $this->log_percent_to_confidence(
                        floatval($segment['avg_logprob'] ?? -1.0)
                    ),
                ],
                $response['segments']
            );
        }
        // Now build the stats for each words.
        foreach ($wordstats as $index => $wordstat) {
            $confidence = 0;
            // Find the corresponding segment to get confidence.
            foreach ($segments as $segment) {
                if ($wordstat['start'] >= $segment['start'] && $wordstat['end'] <= $segment['end']) {
                    $confidence = $segment['confidence'];
                    break;
                }
            }
            $wordstats[$index]['confidence'] = $confidence;
        }
        $response = [
            'success' => true,
            'detectedlanguage' => $response['language'] ?? '',
            'text' => $response['text'] ?? '',
            'stats' => $wordstats,
        ];
        return $response;
    }

    /**
     * Convert log probability to confidence percentage.
     *
     * @param float $logprob The log probability from -1 to 0 (very confident 0, no confident -1).
     * @return int The confidence percentage.
     */
    private function log_percent_to_confidence(float $logprob): int {
        $logprobinvert = 1 + $logprob; // From 0 (no confident) to 1 (very confident).
        return intval(round($logprobinvert * 100));
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
     * @param string $mimetype
     * @param string $fallbackformat
     * @return string
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
        return new Uri(get_config('aiprovider_openai_extension', 'action_convert_speech_to_text_endpoint')) ;
    }

    #[\Override]
    protected function get_model(): string {
        return 'whisper-1'; // For now we use only whisper-1 for speech to text as it is the only one giving the required details.
    }

    /**
     * Get default values
     *
     * @param string $key
     * @return string|null
     */
    protected function get_default(string $key): ?string {
        switch ($key) {
            case 'language':
                return get_config('aiprovider_openai_extension', 'action_convert_speech_to_text_language');
        }
        return null;
    }
}
