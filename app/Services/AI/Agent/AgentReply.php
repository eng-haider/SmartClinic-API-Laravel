<?php

namespace App\Services\AI\Agent;

use App\Models\AiAgentAction;
use App\Services\AI\Agent\Language\Messages;

/**
 * What the chat shows. The kind tells the UI how to present it:
 *  info          data read from the API
 *  clarification a question, often with option buttons
 *  confirmation  an action waiting for the user's approval
 *  success       an action the API carried out
 *  error         an action or lookup that failed
 *  cancelled     an action the user cancelled (nothing changed)
 *  text          anything else (greetings, general answers)
 * The text always reads well on its own, for channels without buttons.
 */
class AgentReply
{
    public const INFO = 'info';
    public const CLARIFICATION = 'clarification';
    public const CONFIRMATION = 'confirmation';
    public const SUCCESS = 'success';
    public const ERROR = 'error';
    public const CANCELLED = 'cancelled';
    public const TEXT = 'text';

    public array $fields = [];
    public array $items = [];
    public array $options = [];
    public array $notes = [];
    public ?array $action = null;
    public ?string $question = null;
    public array $sources = [];

    public function __construct(public string $kind, public string $text, public string $language = 'ar')
    {
    }

    public static function make(string $kind, string $text, string $language): self
    {
        return new self($kind, $text, $language);
    }

    public function field(string $labelKey, ?string $value): self
    {
        if ($value !== null && $value !== '') {
            $this->fields[] = ['label' => Messages::t($labelKey, $this->language), 'value' => $value];
        }

        return $this;
    }

    public function item(string $title, ?string $subtitle = null, ?string $meta = null): self
    {
        $this->items[] = array_filter(['title' => $title, 'subtitle' => $subtitle, 'meta' => $meta], fn ($v) => $v !== null);

        return $this;
    }

    /** @param list<array{id: string, label: string}> $options */
    public function options(array $options): self
    {
        $this->options = array_map(fn ($o) => ['id' => $o['id'], 'label' => $o['label']], $options);

        return $this;
    }

    public function note(string $note): self
    {
        $this->notes[] = $note;

        return $this;
    }

    public function prepend(string $text): self
    {
        $this->text = $text . "\n\n" . $this->text;

        return $this;
    }

    public function withAction(AiAgentAction $action, string $confirmKey): self
    {
        $this->action = [
            'id' => $action->id,
            'tool' => $action->tool,
            'status' => $action->status,
            'expires_at' => $action->expires_at->toIso8601String(),
            'confirm_label' => Messages::t($confirmKey, $this->language),
            'cancel_label' => Messages::t('cancel_btn', $this->language),
        ];

        return $this;
    }

    public function ask(string $question): self
    {
        $this->question = $question;

        return $this;
    }

    /** Plain text with the fields, list, question and notes, for any channel. */
    public function plainText(): string
    {
        $lines = [$this->text];

        if ($this->fields) {
            $lines[] = implode("\n", array_map(fn ($f) => "{$f['label']}: {$f['value']}", $this->fields));
        }
        if ($this->items) {
            $lines[] = implode("\n", array_map(function ($i) {
                $line = '• ' . $i['title'];
                if (!empty($i['subtitle'])) {
                    $line .= ' — ' . $i['subtitle'];
                }
                if (!empty($i['meta'])) {
                    $line .= ' (' . $i['meta'] . ')';
                }

                return $line;
            }, $this->items));
        }
        if ($this->options && !$this->action) {
            $lines[] = implode("\n", array_map(fn ($o, $n) => ($n + 1) . '. ' . $o['label'], $this->options, array_keys($this->options)));
        }
        if ($this->question) {
            $lines[] = $this->question;
        }
        if ($this->action) {
            $lines[] = Messages::t('confirm_hint', $this->language);
        }
        foreach ($this->notes as $note) {
            $lines[] = $note;
        }

        return implode("\n\n", array_filter($lines, fn ($l) => $l !== ''));
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'language' => $this->language,
            'dir' => $this->language === 'en' ? 'ltr' : 'rtl',
            'text' => $this->text,
            'fields' => $this->fields,
            'items' => $this->items,
            'options' => $this->options,
            'question' => $this->question,
            'notes' => $this->notes,
            'action' => $this->action,
            'sources' => $this->sources,
            'plain_text' => $this->plainText(),
        ];
    }
}
