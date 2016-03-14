<?php
error_reporting(0);

$metadatas = $this->GetMetaDatas($this->GetPageTag());

if (isset($metadatas["ebook-title"]) && isset($metadatas["ebook-description"]) && isset($metadatas["ebook-author"]) && isset($metadatas["ebook-biblio-author"]) && isset($metadatas["ebook-cover-image"])) {

	// ePub uses XHTML 1.1, preferably strict.
	$content_start =
	"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
	. "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\n"
	. "    \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n"
	. "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
	. "<head>"
	. "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n"
	. "<link rel=\"stylesheet\" type=\"text/css\" href=\"styles.css\" />\n"
	. "<title>".$metadatas["ebook-title"]."</title>\n"
	. "</head>\n"
	. "<body>\n";

	$bookEnd = "</body>\n</html>\n";


	include_once("tools/tags/libs/tags.functions.php");
	include_once("tools/tags/libs/vendor/PHPePub/EPub.php");

	$book = new EPub();

	// Title and Identifier are mandatory!
	$book->setTitle($metadatas["ebook-title"]);
	$book->setIdentifier($this->href('',$this->getPageTag()), EPub::IDENTIFIER_URI); // Could also be the ISBN number, prefered for published books, or a UUID.
	$book->setLanguage($this->config["lang"]); // Not needed, but included for the example, Language is mandatory, but EPub defaults to "en". Use RFC3066 Language codes, such as "en", "da", "fr" etc.
	$book->setDescription($metadatas["ebook-description"]);
	$book->setAuthor($metadatas["ebook-author"], $metadatas["ebook-author-biblio"]); 
	$book->setPublisher($metadatas["ebook-author"], $this->href('',$this->getPageTag())); // I hope this is a non existant address :) 
	$book->setDate(time()); // Strictly not needed as the book date defaults to time().
	$book->setRights(_t('TAGS_PUBLISHED_UNDER_CREATIVE_COMMONS_BY_SA')); // As this is generated, this _could_ contain the name or licence information of the user who purchased the book, if needed. If this is used that way, the identifier must also be made unique for the book.
	$book->setSourceURL($this->href('',$this->getPageTag()));

	// on concatene tous les styles dans un css
	$styles = $this->Format("{{linkstyle}}");
	preg_match_all('/href="(.*css)"/U', $styles, $matches);
	$cssData = '';
	foreach ($matches[1] as $key => $value) {
		$cssData .= file_get_contents($value);
	}

	$book->addCSSFile("styles.css", "css1", $cssData);

	// This test requires you have an image, change "demo/cover-image.jpg" to match your location.
	$book->setCoverImage("Cover.jpg", file_get_contents($metadatas["ebook-cover-image"]), "image/jpeg");

	// Titre et courte description de l'ouvrage
	$cover = $content_start . '<h1>'.$metadatas["ebook-title"].'</h1>'."\n".'<h2>'._t('TAGS_BY').': '.$metadatas["ebook-author"].'</h2>'."\n" . $metadatas["ebook-description"] . $bookEnd;
	$book->addChapter(_t('TAGS_ABOUT_THIS_EBOOK'), "Cover.html", $cover);

	// on recupere les include pour faire les chapitres 
	preg_match_all("/{{include.*page=\"(.*)\".*class=\"(.*)\".*}}/U", $this->page["body"], $matches);
	foreach ($matches[1] as $nb => $pageWiki) {
		$page = $this->LoadPage($pageWiki);
		$url = explode('wakka.php', $this->config['base_url']);
		if (YW_CHARSET != 'UTF-8') $contentpage = utf8_encode($content_start . str_replace('<img src="'.$url[0], '<img src="', $this->Format('{{include page="'.$pageWiki.'" class="'.$matches[2][$nb].'"}}')) . $bookEnd);
		else $contentpage = $content_start . str_replace('<img src="'.$url[0], '<img src="', $this->Format('{{include page="'.$pageWiki.'" class="'.$matches[2][$nb].'"}}')) . $bookEnd;
		$book->addChapter(get_title_from_body($page), $pageWiki.".html", $contentpage, false, EPub::EXTERNAL_REF_ADD);
	}


	$book->finalize(); // Finalize the book, and build the archive.

	// Save book as a file relative to your script (for local ePub generation)
	// Notice that the extions .epub will be added by the script.
	// The second parameter is a directory name which is '.' by default. Don't use trailing slash!
	$book->saveBook($this->getPageTag(), 'cache');

	// Send the book to the client. ".epub" will be appended if missing.
	$zipData = $book->sendBook($this->getPageTag());
}
else {
	echo $this->Header().'<div class="alert alert-danger">'._t('TAGS_NO_EBOOK_METADATAS').'</div>'.$this->Footer();
}

?>