<?php

namespace JibayMcs\Tabbed\Traits;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use JibayMcs\Tabbed\Enums\HoverCardPosition;

trait HasHoverCard
{
    protected bool $hasHoverCard = false;

    protected ?\Closure $hoverCardContentCallback = null;

    protected HoverCardPosition $hoverCardPosition = HoverCardPosition::Bottom;

    protected int $hoverCardDelay = 600;

    protected int $hoverCardLeaveDelay = 500;

    /**
     * Cache mémoïsé du HTML rendu par `hoverCardContentCallback`, indexé par record key.
     *
     * Filament peut invoquer plusieurs fois la méthode qui construit les données du
     * tab (ex. `alpineClickHandler` + `action()` + attributes Alpine) lors d'un seul
     * render de row → sans cache on rendit la même view 4× par ligne (vu via Debugbar).
     *
     * @var array<string, string>
     */
    protected array $hoverCardRenderedCache = [];

    public function hoverCard(bool $condition = true): static
    {
        $this->hasHoverCard = $condition;

        return $this;
    }

    public function hoverCardContent(\Closure $callback): static
    {
        $this->hoverCardContentCallback = $callback;
        $this->hasHoverCard = true;

        return $this;
    }

    public function hoverCardPosition(HoverCardPosition $position): static
    {
        $this->hoverCardPosition = $position;

        return $this;
    }

    public function hoverCardDelay(int $ms): static
    {
        $this->hoverCardDelay = $ms;

        return $this;
    }

    public function hoverCardLeaveDelay(int $ms): static
    {
        $this->hoverCardLeaveDelay = $ms;

        return $this;
    }

    public function getHasHoverCard(): bool
    {
        return $this->hasHoverCard;
    }

    public function getHoverCardContentCallback(): ?\Closure
    {
        return $this->hoverCardContentCallback;
    }

    public function getHoverCardPosition(): HoverCardPosition
    {
        return $this->hoverCardPosition;
    }

    public function getHoverCardDelay(): int
    {
        return $this->hoverCardDelay;
    }

    public function getHoverCardLeaveDelay(): int
    {
        return $this->hoverCardLeaveDelay;
    }

    /**
     * Résout et mémoïse le HTML du hover card pour un record donné.
     *
     * Retourne null si pas de hover card ou si le rendu jette une exception
     * (le callback du consumer peut accéder à des relations qui n'existent pas).
     */
    public function resolveHoverCardContent(?Model $record): ?string
    {
        if (!$this->hasHoverCard || !$this->hoverCardContentCallback) {
            return null;
        }

        $cacheKey = (string) ($record?->getKey() ?? 'null');

        if (array_key_exists($cacheKey, $this->hoverCardRenderedCache)) {
            return $this->hoverCardRenderedCache[$cacheKey];
        }

        try {
            $content = ($this->hoverCardContentCallback)($record);

            if ($content instanceof View) {
                $content = $content->render();
            } elseif ($content instanceof HtmlString) {
                $content = $content->toHtml();
            }

            return $this->hoverCardRenderedCache[$cacheKey] = (string) $content;
        } catch (\Throwable $e) {
            return $this->hoverCardRenderedCache[$cacheKey] = null;
        }
    }
}
