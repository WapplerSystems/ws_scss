<?php

declare(strict_types=1);

namespace WapplerSystems\WsScss\Event;

final class AfterScssCompilationEvent
{
	public function __construct(
		private string $cssCode
	) {}

	public function getCssCode(): string
	{
		return $this->cssCode;
	}

	public function setCssCode(string $cssCode): void
	{
		$this->cssCode = $cssCode;
	}
}
