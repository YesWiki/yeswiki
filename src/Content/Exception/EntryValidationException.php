<?php

namespace YesWiki\Content\Exception;

/**
 * A submitted entry the visitor can fix: a required field left empty, a form that cannot
 * name its entries.
 *
 * Distinct from every other `\Exception` the save path can throw, which are the site's
 * problem rather than the visitor's. Both used to come out as "an unexpected error
 * occurred, please contact the administrator and quote: champs requis:bf_mail" -- which
 * reads as a crash, names a field by its internal identifier, and appeared above a form
 * that had silently reverted to the stored values, losing what was typed.
 */
class EntryValidationException extends \Exception
{
}
