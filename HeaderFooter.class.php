<?php
/**
 * @package HeaderFooter
 */
class HeaderFooter
{

	public static function hOutputPageParserOutput( OutputPage $op, ParserOutput $parserOutput ): bool {

		global $egHeaderFooterEnableAsyncHeader, $egHeaderFooterEnableAsyncFooter, $egHeaderFooterNamespace;

		$action = $op->getRequest()->getVal("action");
		if ( ($action == 'edit') || ($action == 'submit') || ($action == 'history') ) {
			return true;
		}

		global $wgTitle;

		$ns = $wgTitle->getNsText();
		$name = $wgTitle->getPrefixedDBKey();
		$namespace = $egHeaderFooterNamespace;

		$text = $parserOutput->getText();

		$nsheader = self::conditionalInclude( $text, '__NONSHEADER__', 'hf-nsheader', $ns, $namespace );
		$header   = self::conditionalInclude( $text, '__NOHEADER__',   'hf-header', $name, $namespace );
		$footer   = self::conditionalInclude( $text, '__NOFOOTER__',   'hf-footer', $name, $namespace );
		$nsfooter = self::conditionalInclude( $text, '__NONSFOOTER__', 'hf-nsfooter', $ns, $namespace );

		$parserOutput->setText( $nsheader . $header . $text . $footer . $nsfooter );

		if ( $egHeaderFooterEnableAsyncFooter || $egHeaderFooterEnableAsyncHeader ) {
			$op->addModules( 'ext.headerfooter.dynamicload' );
		}

		return true;
	}

	/**
	 * Verifies & Strips ''disable command'', returns $content if all OK.
	 */
	static function conditionalInclude( &$text, $disableWord, $class, $unique, $namespace ) {

		// is there a disable command lurking around?
		$disable = strpos( $text, $disableWord ) !== false;

		// if there is, get rid of it
		// make sure that the disableWord does not break the REGEX below!
		$text = preg_replace('/'.$disableWord.'/si', '', $text );

		// if there is a disable command, then don't return anything
		if ( $disable ) {
			return null;
		}

		$msgId = "$class-$unique"; // also HTML ID
		$div = "<div class='$class' id='$msgId'>";

		global $egHeaderFooterEnableAsyncHeader, $egHeaderFooterEnableAsyncFooter;

		$isHeader = $class === 'hf-nsheader' || $class === 'hf-header';
		$isFooter = $class === 'hf-nsfooter' || $class === 'hf-footer';

		if ( ( $egHeaderFooterEnableAsyncFooter && $isFooter )
			|| ( $egHeaderFooterEnableAsyncHeader && $isHeader ) ) {

			// Just drop an empty div into the page. Will fill it with async
			// request after page load
			return $div . '</div>';
		} elseif( $namespace !== NS_MEDIAWIKI ) {
			$title = Title::makeTitleSafe( $namespace, $msgId );
			if( $title === null ) {
				return '';
			}

			// Use a new parser to avoid interfering with the current parser.
			$page = RequestContext::getMain()->getTitle();
			$parser = MediaWiki\MediaWikiServices::getInstance()->getParserFactory()->create();
			$parser->startExternalParse( $page, ParserOptions::newFromContext( RequestContext::getMain() ), Parser::OT_HTML );
			$revision = Article::newFromTitle( $title, RequestContext::getMain() )->getPage()->getRevisionRecord();
			if ( !$revision ) {
				// Should not happen
				return '';
			}
			$content = $revision->getContent( MediaWiki\Revision\SlotRecord::MAIN );
			if ( !( $content instanceof TextContent ) ) {
				// Not implemented on non-text contents
				return '';
			}
			$msgText = $parser->parse(
				$content->getText(),
				$page,
				$parser->mOptions
			)->getText();

			// don't need to bother if there is no content.
			if ( empty( $msgText ) ) {
				return '';
			}

			return $div . $msgText . '</div>';
		} else {
			$msgText = wfMessage( $msgId )->parse();

			// don't need to bother if there is no content.
			if ( empty( $msgText ) ) {
				return null;
			}

			if ( wfMessage( $msgId )->inContentLanguage()->isBlank() ) {
				return null;
			}

			return $div . $msgText . '</div>';
		}
	}

	public static function onResourceLoaderGetConfigVars ( array &$vars ) {
		global $egHeaderFooterEnableAsyncHeader, $egHeaderFooterEnableAsyncFooter;

		$vars['egHeaderFooter'] = [
			'enableAsyncHeader' => $egHeaderFooterEnableAsyncHeader,
			'enableAsyncFooter' => $egHeaderFooterEnableAsyncFooter,
		];

		return true;
	}

}
