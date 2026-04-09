<?php

namespace JibayMcs\Tabbed\Traits;

use JibayMcs\Tabbed\Enums\HoverCardPosition;

trait HasHoverCard
{
    protected bool $hasHoverCard = false;

    protected ?\Closure $hoverCardContentCallback = null;

    protected HoverCardPosition $hoverCardPosition = HoverCardPosition::Bottom;

    protected int $hoverCardDelay = 600;

    protected int $hoverCardLeaveDelay = 500;

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
}
