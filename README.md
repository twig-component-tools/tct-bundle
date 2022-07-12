# Twig Component Tools | Loader

Component loader and pre-processor for Twig templates.

## Project Status: ALPHA

* No BC promise
* Changes quickly with internal discussions only
* Open for suggestions and help

## Syntax

Components can be used within regular Twig templates:

```twig
{# File: @components/Page/PHome/PHome.twig #}
{% extends '@components/Teamplate/TBase.twig' %} 

{% block contents %} 
  <AButton
    theme="primary"
    label="{{ 'button.edit_entity'|trans }}"
    enabled
  />
{% endblock %}
```

```twig
{# File: @components/Atom/AButton/AButton.twig #}

<button 
  type="button"
  class="a-button -{{ props.theme|default('grey') }}"
  {% if not enabled %}disabled{% endif %}
>
  {{ props.label }}
</button>
```

## Properties

You can pass different data types to you components. Anything in twig is permitted, as long as it fits between `{{`
and `}}`.
All props can be found in the `props` object within the component.

Properties are passed in `kebab-case` and will be parsed to `camelCase`.

### Strings

Hard-coded values will be passes as strings.

```twig
<ALabel theme="danger"/>
```

### Boolean

properties are `true` if present, "falsy" (null or undefined) if absent. You can also explicitly pass a value.

```twig
<ALabel 
  theme="danger"
  has-options
  is-something="{{ count < 10 }}"
/>
```

### Expressions

```twig
<ALabel
  text-color="{{ themes.dark }}"
  badge="{{ 'label.badge'|trans ~ ' ' ~ count|number_format }}"
  image="{{ 
    [
      theme.default,
      count,
      '.',
      imageFormat
    ]|join('-') 
  }}"
/>
```

### Using Props

Example usage and output from the snippets above:

```twig
{# file: ALabel.twig #}
{{ props.theme }} {# string #}
{{ props.hasOptions }} {# Boolean (true) #}
{{ props.isSomething }} {# Boolean (true/false) #}
{{ props.textColor }} {# mixed (theme.dark) #}
{{ props.badge }} {# string (e.g. 'Profiles: 12' #}
{{ props.image }} {# string (e.g. 'dark-3.jpg' #}
```

### Defaults & constants

Use twigs `merge` method to define default values. Invert the statement to override props and create constants across
all instances of the props.

```twig
{# default values #}
{% set props = {
  theme: 'light',
  count: 0
}|merge(props) %}
```

```twig
{# constant props #}
{% set props = props|merge({
  padding: 3 
}) %}
```

## Blocks

Blocks can be defined and used like in any regular twig template. There are a few syntactic helpers, though:

### Default Blocks

```twig
{# File: @components/Page/PHome/PHome.twig #}
{% extends '@components/Teamplate/TBase.twig' %} 

{% block contents %} 
  <AButton theme="primary">
    {{ 'button.edit_entity'|trans }}
  </AButton>
{% endblock %}
```

```twig
{# File: @components/Atom/AButton/AButton.twig #}

<button type="button" class="a-button -{{ props.theme|default('grey') }}">
  {% block default %}{% endblock %}
</button>
```

### Named Blocks

```twig
{# File: @components/Page/PHome/PHome.twig #}
{% extends '@components/Teamplate/TBase.twig' %} 

{% block contents %} 
  <AButton theme="primary">
    <block name="icon">
      <span>&times;</span>
    </block>
    <block name="label">{{ 'button.edit_entity'|trans }}</block>
  </AButton>
{% endblock %}
```

```twig
{# File: @components/Atom/AButton/AButton.twig #}

<button type="button" class="a-button -{{ props.theme|default('grey') }}">
  {% if block('icon') is defined %}
    <i class="a-button_icon">{{ block('icon')|raw }}</i>
  {% endif %}
  <span class="a-button__label">
    {% block label %}{% endblock %}
  </span>
</button>
```

## Inner workings

Since this component loader works as a pre-processor, it's goal is to accept subjectively easier/better syntax, and to
pass valid Twig syntax to the Twig engine.

Understanding what is transpiled to what can help you master this new syntax:

* Include: Component without blocks
* Embed: Component with blocks
* String parameters: properties without `{{ … }}`
* Variables and expressions: properties with `{{ … }}`

### Examples

TCT In:

```twig
  {% set props = { count: 3 } %}

  <AButton theme="primary">
    <block name="icon">
      <span>{{ props.count }}&nbsp;&times;</span>
    </block>
    <block name="label">{{ 'button.edit_entity'|trans }}</block>
  </AButton>
  
  <AIcon name="{{ random_name() }}"/>
```

Twig Out:

```twig
 {% set props = { count: 3 } %}

 {% embed '@components/Atom/AButton/AButton.twig' with { props: { theme: "primary" }, embedContext: _context } only %}
    {% block icon %}
      {% with embedContext %}
        <span>{{ props.count }}&nbsp;&times;</span>
      {% endwith %}
    {% endblock %}
    
    {% block label %}
      {% with embedContext %}
        {{ 'button.edit_entity'|trans }}
      {% endwith %}
    {% endblock %}
 {% endembed %} 
 
 {% include '@components/Atom/AIcon/AIcon.twig' with { name: random_name() } only %}
```

### Reasoning

This preprocessor aims to make developing and reviewing Twig templates easier and faster.

I have always been displeased with the time it takes to scan and understand bigger Twig projects.
Working with partials and components is useful, but there is not enough visual distinction between **control**
statements: `{% if … %}`, `{% for … %}`, `{% set … %}` and **markup/composition**: `{% include … %}`, `{% embed … %}`.
[Customizing the syntax](https://twig.symfony.com/doc/2.x/recipes.html#customizing-the-syntax) doesn't quite cut it for
me.
