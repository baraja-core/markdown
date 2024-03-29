<?php

declare(strict_types=1);

namespace Baraja\Markdown;


use Nette\Utils\Strings;

final class Route
{
	public const
		DEFAULT_PRESENTER = 'Homepage',
		DEFAULT_ACTION = 'default',
		DEFAULT_ROUTE = 'Homepage:default';

	private const PATTERN = '/^(?:(?<module>[A-Za-z]*):)?(?<presenter>[A-Za-z]*):(?<action>[A-Za-z]+)(?<params>\,*?.*?)$/';

	private ?string $module;

	private string $presenterName;

	private string $actionName;

	private ?string $id;

	/** @var string[] */
	private array $params;


	/**
	 * @param string[] $params
	 */
	public function __construct(
		string $module = null,
		string $presenter = self::DEFAULT_PRESENTER,
		string $action = self::DEFAULT_ACTION,
		string $id = null,
		array $params = [],
	) {
		$this->module = $module !== '' ? $module : null;
		$presenterName = trim(Strings::firstUpper($presenter !== '' ? $presenter : self::DEFAULT_PRESENTER), '/');
		$actionName = trim(Strings::firstLower($action !== '' ? $action : self::DEFAULT_ACTION), '/');
		$this->presenterName = $presenterName !== '' ? $presenterName : self::DEFAULT_PRESENTER;
		$this->actionName = $actionName !== '' ? $actionName : self::DEFAULT_ACTION;
		$this->id = $id !== '' && $id !== null ? trim($id, '/') : null;
		$this->params = $params;
	}


	/**
	 * @param string $pattern in format "[Module:]Presenter:action, id => 123, param => value, foo => bar"
	 */
	public static function createByPattern(string $pattern): self
	{
		if (preg_match(self::PATTERN, trim($pattern, ':'), $patternParser) !== 1) {
			throw new \InvalidArgumentException('Invalid link "' . htmlspecialchars($pattern) . '". Did you mean format "Presenter:action" or "Module:Presenter:action"?');
		}

		$id = null;
		$params = [];
		foreach (explode(',', trim($patternParser['params'], ', ')) as $param) {
			if (preg_match('/^(?<key>[\'"]?\w+[\'"]?)\s*=>\s*(?<value>.*)$/', trim($param), $paramParser) === 1) {
				$paramKey = trim($paramParser['key'], '\'"');
				if ($paramKey === 'id') {
					$id = $paramParser['value'];
				}
				$params[$paramKey] = trim($paramParser['value'], '\'"');
			}
		}

		return new self(
			$patternParser['module'] ?? null,
			$patternParser['presenter'],
			$patternParser['action'],
			$id,
			$params,
		);
	}


	public function __toString(): string
	{
		return $this->toString();
	}


	/**
	 * Return formats:
	 *    Presenter:action
	 *    Presenter:action, id => 123
	 *    Presenter:action, id => 123, param => value, foo => bar
	 */
	public function toString(): string
	{
		$returnParams = array_merge(
			$this->params,
			$this->id !== null ? ['id' => $this->id] : [],
		);

		$return = Strings::firstUpper($this->presenterName) . ':' . $this->actionName;
		foreach ($returnParams as $paramKey => $paramValue) {
			$return .= ', ' . $paramKey . ' => ' . $paramValue;
		}

		return $return;
	}


	public function getModule(): ?string
	{
		return $this->module;
	}


	public function getPresenterName(bool $withModule = true): string
	{
		if ($withModule === true) {
			$module = $this->module === null || trim($this->module) === ''
				? 'Front:'
				: $this->module . ':';

			return $module . $this->presenterName;
		}

		return $this->presenterName;
	}


	public function getActionName(): string
	{
		return $this->actionName;
	}


	public function isDefault(): bool
	{
		return $this->getActionName() === self::DEFAULT_ACTION;
	}


	public function getId(): int|string|null
	{
		return $this->isNumericInt($this->id)
			? (int) $this->id
			: $this->id;
	}


	/**
	 * @return mixed[]
	 */
	public function getParams(): array
	{
		$return = [];
		foreach ($this->params as $key => $value) {
			$return[$key] = $this->isNumericInt($value)
				? (int) $value
				: $value;
		}

		return $return;
	}


	private function isNumericInt(mixed $value): bool
	{
		return is_int($value) || (is_string($value) && preg_match('#^-?[\d]+\z#', $value) === 1);
	}
}
