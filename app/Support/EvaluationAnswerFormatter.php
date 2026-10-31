<?php

namespace App\Support;

use App\Models\Question;

class EvaluationAnswerFormatter
{
    public static function correctAnswer(Question $question): string
    {
        $answer = trim((string) $question->correct_answer);
        $options = self::options($question);

        if (! $options) {
            return $answer;
        }

        foreach ($options as $option) {
            if (self::normalize($option['value']) === self::normalize($answer)
                || self::normalize($option['label']) === self::normalize($answer)) {
                return self::labelled($option);
            }
        }

        if (is_numeric($answer)) {
            $index = (int) $answer === 0 ? 0 : (int) $answer - 1;
            if (isset($options[$index])) {
                return self::labelled($options[$index]);
            }
        }

        return $answer;
    }

    public static function submittedAnswer(Question $question, string $answer): string
    {
        $options = self::options($question);
        if (! $options) {
            return $answer;
        }

        foreach ($options as $option) {
            if (self::normalize($option['value']) === self::normalize($answer)
                || self::normalize($option['label']) === self::normalize($answer)) {
                return self::labelled($option);
            }
        }

        if (is_numeric($answer)) {
            $index = (int) $answer;
            if (isset($options[$index])) {
                return self::labelled($options[$index]);
            }
            if ((int) $answer > 0 && isset($options[(int) $answer - 1])) {
                return self::labelled($options[(int) $answer - 1]);
            }
        }

        return $answer;
    }

    public static function isCorrect(Question $question, string $answer): bool
    {
        $normalizedAnswer = self::normalize($answer);
        $normalizedCorrect = self::normalize(trim((string) $question->correct_answer));

        // Coincidencia directa con la clave cruda (ej. mismo índice de opción o mismo texto).
        // Evita que correctAnswer() (1-based) y submittedAnswer() (0-based primero)
        // resuelvan índices numéricos distintos y marquen incorrecta la clave misma.
        if ($normalizedAnswer !== '' && $normalizedAnswer === $normalizedCorrect) {
            return true;
        }

        return self::normalize(self::correctAnswer($question))
            === self::normalize(self::submittedAnswer($question, $answer));
    }

    private static function options(Question $question): array
    {
        $options = $question->options ?? [];
        for ($attempt = 0; $attempt < 2 && is_string($options); $attempt++) {
            $decoded = json_decode($options, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
            $options = $decoded;
        }

        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->values()
            ->map(function ($option, $index) {
                if (is_string($option)) {
                    return ['label' => '', 'value' => $option];
                }

                return [
                    'label' => trim((string) ($option['label'] ?? '')),
                    'value' => trim((string) ($option['value'] ?? '')),
                ];
            })
            ->filter(fn ($option) => $option['value'] !== '')
            ->all();
    }

    private static function labelled(array $option): string
    {
        return $option['label'] !== ''
            ? "{$option['label']}. {$option['value']}"
            : $option['value'];
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', '', $value);

        return str_replace(['×', '÷', '²', '³'], ['*', '/', '^2', '^3'], $value);
    }
}
