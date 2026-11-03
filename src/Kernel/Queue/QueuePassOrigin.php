<?php

namespace YesWiki\Kernel\Queue;

/**
 * What started a pass (the mode's consumer, a request's pinned messages, a manual run, the fallback), which its row
 * keeps: only the consumer the admin chose proves that consumer is at work (QueueFallback).
 */
enum QueuePassOrigin: string
{
    case Consumer = 'consumer';
    case Pinned = 'pinned';
    case Manual = 'manual';
    case Fallback = 'fallback';
}
