# Welcome to the contributing guide for YesWiki

Interesting in contributing? Awesome!

**Quick Links:**

- [Give your feedback](#give-your-feedback)
- [Write documentation](#write-documentation)
- [Develop](#develop)

## Give your feedback

You don't need to know how to code to start contributing to YesWiki! Other
contributions are very valuable too, among which: you can test the software and
report bugs, you can give feedback on potential bugs, features that you are
interested in, user interface, design, ...

For the french speaking, [go on the YesWiki site to see all possible contribution areas](https://yeswiki.net/?TacheS)

## Write documentation

You can help to write the [french documentation of YesWiki](https://yeswiki.net/?DocumentatioN) or start to translate it to other languages!

## Develop

Don't hesitate to talk about features you want to develop by creating/commenting an issue
before you start working on them :).

for the french speaking, [check the YesWiki page about development](https://yeswiki.net/?DeveloppemenT)

### Prerequisites

First, make sure that you have a web server (nginx, apache, ..), PHP >= 5.5 (with mysql, curl, gd extensions activated) and Mysql server

Then clone the sources:

```
$ git clone https://github.com/YesWiki/yeswiki.git
```

Then, just go in the browser to start the installation procedure.

### Coding standards

It's a work in progress but we try to follow the [PHP PSR-2 standards](https://www.php-fig.org/psr/psr-2/).  
As for Javascript, a linter (like eslint) can be used to check the code.
