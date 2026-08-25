<?php

namespace YesWiki\Kernel\Entity;

/**
 * The two populations the Journal holds: what someone did, and what broke (ADR-0025). They share columns and nothing else -- an audit entry never collapses and is kept for a year, a diagnostic collapses on its fingerprint and is kept for a fortnight.
 */
enum JournalChannel: string
{
    case Audit = 'audit';
    case Diagnostic = 'diagnostic';
}
