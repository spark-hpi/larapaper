<?php

declare(strict_types=1);

use App\Liquid\FileSystems\InlineTemplatesFileSystem;
use App\Liquid\Filters\Data;
use App\Liquid\Filters\Date;
use App\Liquid\Filters\Localization;
use App\Liquid\Filters\Numbers;
use App\Liquid\Filters\StringMarkup;
use App\Liquid\Filters\Uniqueness;
use App\Liquid\Tags\TemplateTag;
use Keepsuit\Liquid\Environment;
use Keepsuit\Liquid\Exceptions\LiquidException;
use Keepsuit\Liquid\Extensions\StandardExtension;
use Keepsuit\Liquid\Tags\RenderTag;

beforeEach(function (): void {
    $this->fileSystem = new InlineTemplatesFileSystem();
    $this->environment = new Environment(
        fileSystem: $this->fileSystem,
        extensions: [new StandardExtension()]
    );
    $this->environment->tagRegistry->register(TemplateTag::class);
    $this->environment->tagRegistry->register(RenderTag::class);
    $this->environment->filterRegistry->register(Data::class);
    $this->environment->filterRegistry->register(Date::class);
    $this->environment->filterRegistry->register(Localization::class);
    $this->environment->filterRegistry->register(Numbers::class);
    $this->environment->filterRegistry->register(StringMarkup::class);
    $this->environment->filterRegistry->register(Uniqueness::class);
});

test('template tag registers template', function (): void {
    $template = $this->environment->parseString(<<<'LIQUID'
{% template session %}
<div class="layout">
  <div class="columns">
    <div class="column">
      <div class="markdown gap--large">
        <div class="value{{ size_mod }} text--center">
          {{ facts[randomNumber] }}
        </div>
      </div>
    </div>
  </div>
</div>
{% endtemplate %}
LIQUID
    );

    $context = $this->environment->newRenderContext(
        data: [
            'facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
            'randomNumber' => 1,
            'size_mod' => '--large',
        ]
    );

    $result = $template->render($context);

    expect($result)->toBe('')
        ->and($this->fileSystem->hasTemplate('session'))->toBeTrue();

    $registeredTemplate = $this->fileSystem->readTemplateFile('session');
    expect($registeredTemplate)->toContain('{{ facts[randomNumber] }}')
        ->toContain('{{ size_mod }}');
});

test('template tag with render tag', function (): void {
    $template = $this->environment->parseString(<<<'LIQUID'
{% template session %}
<div class="layout">
  <div class="columns">
    <div class="column">
      <div class="markdown gap--large">
        <div class="value{{ size_mod }} text--center">
          {{ facts[randomNumber] }}
        </div>
      </div>
    </div>
  </div>
</div>
{% endtemplate %}

{% render "session",
  trmnl: trmnl,
  facts: facts,
  randomNumber: randomNumber,
  size_mod: ""
%}
LIQUID
    );

    $context = $this->environment->newRenderContext(
        data: [
            'facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
            'randomNumber' => 1,
            'trmnl' => ['plugin_settings' => ['instance_name' => 'Test']],
        ]
    );

    $result = $template->render($context);

    expect($result)->toContain('Fact 2')
        ->toContain('class="layout"')
        ->toContain('class="value text--center"');
});

test('apply liquid replacements converts with syntax', function (): void {
    $originalLiquid = <<<'LIQUID'
{% template session %}
<div class="layout">
  <div class="columns">
    <div class="column">
      <div class="markdown gap--large">
        <div class="value{{ size_mod }} text--center">
          {{ facts[randomNumber] }}
        </div>
      </div>
    </div>
  </div>
</div>
{% endtemplate %}

{% render "session" with
  trmnl: trmnl,
  facts: facts,
  randomNumber: randomNumber,
  size_mod: ""
%}
LIQUID;

    $convertedLiquid = preg_replace(
        '/{%\s*render\s+([^}]+?)\s+with\s+/i',
        '{% render $1, ',
        $originalLiquid
    );

    expect($convertedLiquid)->toContain('{% render "session",')->not->toContain('{% render "session" with')
        ->toContain('trmnl: trmnl,')
        ->toContain('facts: facts,');
});

test('template tag with render with tag', function (): void {
    $originalLiquid = <<<'LIQUID'
{% template session %}
<div class="layout">
  <div class="columns">
    <div class="column">
      <div class="markdown gap--large">
        <div class="value{{ size_mod }} text--center">
          {{ facts[randomNumber] }}
        </div>
      </div>
    </div>
  </div>
</div>
{% endtemplate %}

{% render "session" with
  trmnl: trmnl,
  facts: facts,
  randomNumber: randomNumber,
  size_mod: ""
%}
LIQUID;

    $convertedLiquid = preg_replace(
        '/{%\s*render\s+([^}]+?)\s+with\s+/i',
        '{% render $1, ',
        $originalLiquid
    );

    $template = $this->environment->parseString($convertedLiquid);

    $context = $this->environment->newRenderContext(
        data: [
            'facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
            'randomNumber' => 1,
            'trmnl' => ['plugin_settings' => ['instance_name' => 'Test']],
        ]
    );

    $result = $template->render($context);

    expect($result)->toContain('Fact 2')
        ->toContain('class="layout"')
        ->toContain('class="value text--center"');
});

test('template tag with multiple templates', function (): void {
    $template = $this->environment->parseString(<<<'LIQUID'
{% template session %}
<div class="layout">
  <div class="columns">
    <div class="column">
      <div class="markdown gap--large">
        <div class="value{{ size_mod }} text--center">
          {{ facts[randomNumber] }}
        </div>
      </div>
    </div>
  </div>
</div>
{% endtemplate %}

{% template title_bar %}
<div class="title_bar">
  <img class="image" src="https://res.jwq.lol/img/lumon.svg">
  <span class="title">{{ trmnl.plugin_settings.instance_name }}</span>
  <span class="instance">{{ instance }}</span>
</div>
{% endtemplate %}

<div class="view view--{{ size }}">
{% render "session",
  trmnl: trmnl,
  facts: facts,
  randomNumber: randomNumber,
  size_mod: ""
%}

{% render "title_bar",
  trmnl: trmnl,
  instance: "Please try to enjoy each fact equally."
%}
</div>
LIQUID
    );

    $context = $this->environment->newRenderContext(
        data: [
            'size' => 'full',
            'facts' => ['Fact 1', 'Fact 2', 'Fact 3'],
            'randomNumber' => 1,
            'trmnl' => ['plugin_settings' => ['instance_name' => 'Test Plugin']],
        ]
    );

    $result = $template->render($context);

    expect($result)->toContain('Fact 2')
        ->toContain('Test Plugin')
        ->toContain('Please try to enjoy each fact equally')
        ->toContain('class="view view--full"');
});

test('template tag invalid name', function (): void {
    expect(function (): void {
        $template = $this->environment->parseString(<<<'LIQUID'
{% template invalid-name %}
<div>Content</div>
{% endtemplate %}
LIQUID
        );

        $context = $this->environment->newRenderContext();

        $template->render($context);
    })->toThrow(LiquidException::class);
});

test('template tag without file system', function (): void {
    $template = $this->environment->parseString(<<<'LIQUID'
{% template session %}
<div>Content</div>
{% endtemplate %}
LIQUID
    );

    $context = $this->environment->newRenderContext();

    $result = $template->render($context);

    expect($result)->toBe('');
});

test('quotes template with modulo filter', function (): void {
    $template = $this->environment->parseString(<<<'LIQUID'
{% assign quotes_array = quotes[trmnl.plugin_settings.custom_fields_values.language] %}
{% assign random_index = 'now' | date: '%s' | modulo: quotes_array.size %}
{{ quotes_array[random_index] }}
LIQUID
    );

    $context = $this->environment->newRenderContext(
        data: [
            'quotes' => [
                'english' => ['Demo Quote'],
                'german' => ['Demo Zitat'],
            ],
            'trmnl' => [
                'plugin_settings' => [
                    'custom_fields_values' => [
                        'language' => 'english',
                    ],
                ],
            ],
        ]
    );

    $result = $template->render($context);

    expect($result)->toContain('Demo Quote')->not->toContain('Demo Zitat');
});
