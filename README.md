# Twig Component Tools | Loader

Component loader and pre-processor for Twig templates.

## Syntax

Components can be used within regular Twig templates:

```twig
{# File: @components/Page/PHome/PHome.twig #}
{% extends '@components/Teamplate/TBase.twig' %} 

{% block contents %} 
  <AButton
    theme="primary"
    label="{{ 'button.edit_entity'|trans }}"
  />
{% endblock %}
```

```twig
{# File: @components/Atom/AButton/AButton.twig #}

<button type="button" class="a-button -{{ theme|default('grey') }}">
  {{ label }}
</button>
```

## Properties

Props passed to your components can be either hard-coded strings (example: `theme`), or variables and expressions (
example: `level`).

```twig
<ALabel
  theme="danger"
  level="{{ errors|count * 10 }}"
/>
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

<button type="button" class="a-button -{{ theme|default('grey') }}">
  {% block AButton_default %}{% endblock %}
</button>
```

### Named Blocks

```twig
{# File: @components/Page/PHome/PHome.twig #}
{% extends '@components/Teamplate/TBase.twig' %} 

{% block contents %} 
  <AButton theme="primary">
    <block #icon>
      <span>&times;</span>
    </block>
    <block #label>{{ 'button.edit_entity'|trans }}</block>
  </AButton>
{% endblock %}
```

```twig
{# File: @components/Atom/AButton/AButton.twig #}

<button type="button" class="a-button -{{ theme|default('grey') }}">
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
  <AButton theme="primary">
    <block #icon>
      <span>&times;</span>
    </block>
    <block #label>{{ 'button.edit_entity'|trans }}</block>
  </AButton>
  
  <AIcon name="{{ random_name() }}"/>
```

Twig Out:

```twig
 {% embed '@components/Atom/AButton/AButton.twig' with { theme: "primary" } only %}
    {% block icon %}
      <span>&times;</span>
    {% endblock %}
    
    {% block label %}
      {{ 'button.edit_entity'|trans }}
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
