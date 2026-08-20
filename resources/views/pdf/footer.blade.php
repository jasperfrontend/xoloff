{{--
    Repeated at the foot of every page by Gotenberg.

    SPEC §6 says page numbering is automatic and needs no extra work. That is
    not quite true twice over. Chromium fills the numbers in, but only into a
    footer document that is supplied - and it renders that document with a font
    size of zero unless every element carries an explicit one. Without the sizes
    below the footer is present and invisible, which is exactly how it shipped
    until it was rendered against the real container.

    Sizes are in px on each element deliberately: a size on body alone does not
    survive, and pt units are not honoured here either. Inline rather than in a
    stylesheet, because this fragment is rendered on its own.
--}}
<html lang="nl">
<body style="margin: 0; font-size: 9px;">
<div style="width: 100%; padding: 0 16mm; text-align: right; font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #5b6472;">
    Pagina <span class="pageNumber" style="font-size: 9px;"></span>
    van <span class="totalPages" style="font-size: 9px;"></span>
</div>
</body>
</html>
