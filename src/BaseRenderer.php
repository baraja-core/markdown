<?php

declare(strict_types=1);

namespace Baraja\Markdown;


use Baraja\Url\Url;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Localization\Translator;
use Nette\Utils\Strings;

abstract class BaseRenderer implements Renderer
{
	private ?string $baseUrl = null;

	/** @var array<int, string> */
	private array $safeDomains = ['baraja.cz'];


	public function __construct(
		private LinkGenerator $linkGenerator,
		private ?Translator $translator = null,
	) {
	}


	public function setTranslator(Translator $translator): void
	{
		$this->translator = $translator;
	}


	public function setBaseUrl(string $baseUrl): void
	{
		$this->baseUrl = $baseUrl;
	}


	/**
	 * @param array<int, string> $safeDomains
	 */
	public function setSafeDomains(array $safeDomains): void
	{
		$this->safeDomains = $safeDomains;
	}


	protected function process(string $content): string
	{
		$baseUrl = $this->resolveBaseUrl();
		$content = (string) preg_replace(
			'/\{\$(?:basePath|baseUrl)\}\/?/',
			rtrim($baseUrl, '/') . '/',
			$content,
		);

		$content = (string) preg_replace_callback( // <a href="..."> HTML links
			'/ href="\/(?<link>[^"]*)"/',
			static fn(array $match): string => ' href="' . $baseUrl . '/' . $match['link'] . '"',
			$content,
		);

		$content = (string) preg_replace_callback( // n:href="..." Nette links
			'/n:href="(?<link>[^"]*)"/',
			function (array $match): string {
				try {
					$route = Route::createByPattern($match['link']);

					return 'href="' . $this->linkGenerator->link(
						$route->getPresenterName() . ':' . $route->getActionName(),
						$route->getParams(),
					) . '"';
				} catch (InvalidLinkException | \InvalidArgumentException $e) {
					trigger_error($e->getMessage());

					return 'href="#INVALID_LINK"';
				}
			},
			$content,
		);

		$ignoreContents = [];
		$content = (string) preg_replace_callback(
			'/((?:<pre>)?<code(?:\s+[^>]+|)>(?:.|\n)*?<\/code\s*>(?:<\/pre>)?)/',
			static function (array $match) use (&$ignoreContents): string {
				$ignoreContents[] = $match[0] ?? '';

				return '[[ignore-content-' . (count($ignoreContents) - 1) . ']]';
			},
			$content,
		);

		$content = (string) preg_replace_callback( // URL inside text
			'/(.|\n|^)((?i)\b(?:(?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'\\\\".,<>?«»“”‘’])))/u',
			static fn(array $match): string => $match[1] === '"' || $match[1] === '\''
				? $match[0]
				: $match[1] . $this->renderLink(htmlspecialchars_decode(trim($match[2] ?? '')), $baseUrl),
			$content,
		);

		$content = (string) preg_replace_callback(
			'/\[\[ignore-content-(\d+)]]/',
			static fn(array $match): string => $ignoreContents[(int) ($match[1] ?? 0)] ?? '',
			$content,
		);

		$content = (string) preg_replace_callback( // Translate macros
			'/(\{_\})(?<haystack>.+?)(\{\/_\})/', // {_}hello{/_}
			fn(array $match): string => $this->getTranslator()->translate($match['haystack']),
			$content,
		);

		$content = (string) preg_replace_callback( // Alternative translate macros
			'/\{_(?:(?<haystack>.*?))\}/', // {_hello}, {_'hello'}, {_"hello"}
			fn(array $match): string => $this->getTranslator()->translate(trim($match['haystack'], '\'"')),
			$content,
		);

		return $content;
	}


	protected function resolveBaseUrl(): string
	{
		return $this->baseUrl ?? Url::get()->getBaseUrl();
	}


	protected function renderLink(string $url, string $baseUrl): string
	{
		$urlDomain = MarkdownHelper::parseDomain($url);
		if ($urlDomain === null) {
			return $url;
		}
		$baseUrlDomain = MarkdownHelper::parseDomain($baseUrl);
		$external = $urlDomain !== $baseUrlDomain && !in_array($urlDomain, $this->safeDomains, true);
		$safeUrl = MarkdownHelper::safeUrl($url);
		if ($safeUrl === '') {
			trigger_error('Given URL is not safe, because string "' . $url . '" given.');
		}

		return '<a href="' . MarkdownHelper::escapeHtmlAttr($safeUrl) . '"'
			. ($external ? ' target="_blank" rel="nofollow"' : '')
			. '>'
			. html_entity_decode(
				(string) preg_replace_callback(
					'/^(https?:\/\/[^\/]+)(.*)$/',
					static fn(array $part): string => $part[1] . Strings::truncate($part[2], 32),
					strip_tags($url),
				),
				ENT_QUOTES | ENT_HTML5,
				'UTF-8',
			)
			. '</a>';
	}


	private function getTranslator(): Translator
	{
		if ($this->translator === null) {
			throw new \RuntimeException('Translator service does not set. Did you call ->setTranslator()?');
		}

		return $this->translator;
	}
}
