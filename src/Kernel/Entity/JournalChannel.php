<?php

namespace YesWiki\Kernel\Entity;

/**
 * The three populations the Journal holds: what someone did, what broke (ADR-0025), and what the background queue ran. They share columns and nothing else -- an audit entry never collapses and is kept for a year, a diagnostic collapses on its fingerprint and is kept for a fortnight, and a queue row is written by the queue itself, dated in UTC by PHP, and kept for a week from its last sign of life.
 */
enum JournalChannel: string
{
    case Audit = 'audit';
    case Diagnostic = 'diagnostic';
    case Queue = 'queue';
}
