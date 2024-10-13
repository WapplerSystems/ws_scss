<?php

declare(strict_types=1);

namespace WapplerSystems\WsScss\Event;

final class AfterVariableDefinitionEvent
{
	public function __construct(private array $variables)
	{
	}

	public function getVariables(): array
	{
		return $this->variables;
	}

	public function setVariables(array $variables): void
	{
		$this->variables = $variables;
	}
}
