{{--
    Repeated at the foot of every page by Gotenberg.

    SPEC §6 says page numbering is automatic and needs no extra work. It is
    automatic in the sense that Chromium fills the numbers in, but only into a
    footer document that is supplied - without this file the pages come out
    unnumbered. Gotenberg requires the classes below verbatim, and inline styles
    rather than a stylesheet, because this fragment is rendered on its own.
--}}
<html lang="nl">
<body style="margin: 0; font-family: 'Helvetica Neue', Helvetica, Arial, 'DejaVu Sans', sans-serif; font-size: 8pt; color: #5b6472;">
<div style="width: 100%; padding: 0 16mm; text-align: right;">
    Pagina <span class="pageNumber"></span> van <span class="totalPages"></span>
</div>
</body>
</html>
