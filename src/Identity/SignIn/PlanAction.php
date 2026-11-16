<?php

namespace YesWiki\Identity\SignIn;

/** What a sign-in will do to the wiki's accounts. */
enum PlanAction: string
{
    case UseLinked = 'use_linked';
    case LinkExisting = 'link_existing';
    case ReplaceSubject = 'replace_subject';
    case Create = 'create';
    case UseLocal = 'use_local';
    case Refuse = 'refuse';
}
