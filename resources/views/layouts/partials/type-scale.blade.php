{{--
    Global type scale — every Tailwind text size two steps larger than the default
    (xs 12→14px, sm 14→16px, base 16→18px, …) so the whole app reads comfortably
    without touching spacing or layout widths. Include this right AFTER a page's
    own `tailwind.config = {...}` script; it merges into that config rather than
    replacing it, so brand colours / fonts set per layout are preserved.
--}}
<script>
    (function () {
        window.tailwind = window.tailwind || {};
        var c = tailwind.config = tailwind.config || {};
        c.theme = c.theme || {};
        c.theme.extend = c.theme.extend || {};
        c.theme.extend.fontSize = Object.assign({
            'xs':   ['0.875rem',  { lineHeight: '1.25rem' }],   // 14px
            'sm':   ['1rem',      { lineHeight: '1.5rem' }],    // 16px
            'base': ['1.125rem',  { lineHeight: '1.75rem' }],   // 18px
            'lg':   ['1.25rem',   { lineHeight: '1.75rem' }],   // 20px
            'xl':   ['1.375rem',  { lineHeight: '1.875rem' }],  // 22px
            '2xl':  ['1.75rem',   { lineHeight: '2.125rem' }],  // 28px
            '3xl':  ['2.125rem',  { lineHeight: '2.5rem' }],    // 34px
        }, c.theme.extend.fontSize || {});
    })();
</script>
<style>
    /* Hard-coded micro labels (text-[10px] / text-[11px]) sit outside the scale — lift them one step too.
       `html` prefix out-ranks Tailwind's generated single-class rule regardless of stylesheet order. */
    html .text-\[10px\] { font-size: 12px; }
    html .text-\[11px\] { font-size: 13px; }
</style>
