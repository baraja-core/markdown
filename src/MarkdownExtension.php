<?php

declare(strict_types=1);

namespace Baraja\Markdown;


use Nette\DI\CompilerExtension;

final class MarkdownExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('commonMarkRenderer'))
			->setFactory(CommonMarkRenderer::class);

		$builder->addDefinition($this->prefix('markdown'))
			->setFactory(Markdown::class);

		$builder->addDefinition($this->prefix('converterAccessor'))
			->setFactory(ConverterAccessor::class);
	}
}
