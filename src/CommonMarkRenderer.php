<?php

declare(strict_types=1);

namespace Baraja\Markdown;


final class CommonMarkRenderer
{
	public function __construct(
		private Markdown $markdown,
	) {
	}


	public function render(string|\Stringable $content): string
	{
		return $this->markdown->render($content);
	}
}
