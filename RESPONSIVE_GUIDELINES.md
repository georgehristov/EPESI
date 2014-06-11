Guidelines
==========

We'd like to rewrite GUI to use Twitter's Bootstrap.

If you'd like to help us to get a new look, then you're welcome to fork and help.


Decisions made
--------------

We've decided to include less files from Bootstrap (not compiled and minified css),
because IDE (like great [PhpStorm][] we're using) will help
with auto-completion in Less files.
In this situation we can generate a single CSS file with all bootstrap
and our own styles.

Backend templating stays as is - we're using Smarty templates to generate HTML.

We haven't decided yet how EPESI should look like, so it's the open case right now.
All we know is that it would be nice to make it themeable.


To coordinate our work, we propose following rules
--------------------------------------------------

1. First rewrite HTML to a clean html5 markup with a good structure.
  - Get rid out of all styles in code - use classes or ids when necessary
      to apply styles later.
  - Use [custom elements][] if you wish. It's nice and clean.

2. Create corresponding classes in [LESS][] files.
  - Please don't use built-in bootstrap classes. Read [this great article][bootstrap classes].
  - Hold button, label, headers, frames, colors, etc in separate Less files.
    Create a new one if you need it.
  - Try to make it generic and easy to change.
    For instance use variables
    to store app colors. Use color darken and lighten functions to generate
    gradients, because it's easy to change base color in one place.
  - If some classes are very module specific then use modules theme css/less
    files to define them. If you think that this class may be used anywhere else
    in the future than put it in the global style.

3. Make a class style with Less and mixins.
   For example:

```CSS
.button {
  .btn;
  .btn-default;
  .btn-lg;
}
```


Developers
----------

You need to compile Less files to css on your own.
Many IDE will do that for you if you've set them properly.

For the release we'll compile Less files to CSS and include only the latter.


Discussion
----------

Discussion about styles and any other things related to this branch should
be made on [this dedicated board][forum board] (It'll be created soon)

If you've got any suggestion or you think that we've made wrong decisions, that
may lead to some code smells and anti-pattern, then please contact us!
We're open for any help and suggestions.


[custom elements]: http://www.html5rocks.com/en/tutorials/webcomponents/customelements/
[LESS]: http://lesscss.org/
[PhpStorm]: http://www.jetbrains.com/phpstorm/
[bootstrap classes]: http://ruby.bvision.com/blog/please-stop-embedding-bootstrap-classes-in-your-html
[forum board]: http://forum.epesibim.com/