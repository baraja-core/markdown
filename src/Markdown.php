<?php

declare(strict_types=1);

namespace Baraja\Markdown;


use Nette\Application\LinkGenerator;

final class Markdown extends BaseRenderer
{
	/** @var array<string, string> */
	private static array $helpers = [
		'\(' => 'LATEX-L',
		'\)' => 'LATEX-R',
	];

	private ConverterAccessor $commonMarkConverter;


	public function __construct(
		?ConverterAccessor $commonMarkConverter = null,
		?LinkGenerator $linkGenerator = null,
	) {
		$this->commonMarkConverter = $commonMarkConverter ?? new ConverterAccessor;
		parent::__construct($linkGenerator);
	}


	public function render(string|\Stringable $content): string
	{
		$content = (string) $content;
		static $cache = [];
		if (isset($cache[$content]) === false) {
			$html = $this->process(
				$this->commonMarkConverter
					->get()
					->convert($this->beforeProcess($content))
					->getContent(),
			);

			$baseUrl = $this->resolveBaseUrl();
			$html = preg_replace_callback(
				'/src="\/?((?:img|static)\/([^"]+))"/',
				static fn(array $match): string => sprintf('src="%s/%s"', $baseUrl, $match[1]),
				$this->afterProcess($html),
			);

			$cache[$content] = $html;
		}

		return $cache[$content];
	}


	private function beforeProcess(string $haystack): string
	{
		foreach (self::$helpers as $key => $value) {
			$haystack = str_replace($key, $value, $haystack);
		}
		$haystack = (string) preg_replace('/([a-z"])>{2,}/', '$1>', $haystack);
		$haystack = (string) preg_replace('/<{2,}([a-z\/])/', '<$1', $haystack);

		return $haystack;
	}


	private function afterProcess(string $haystack): string
	{
		foreach (self::$helpers as $key => $value) {
			$haystack = str_replace($value, $key, $haystack);
		}

		return $haystack;
	}
}
