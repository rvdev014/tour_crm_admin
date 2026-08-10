import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        // Was scoped to resources/views/filament/** only, so Tailwind classes used in
        // resources/views/actions/*, resources/views/tables/columns/* and errors/* were
        // being purged from the production build. Widened to the whole views tree.
        './resources/views/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            // Single radius/shadow scale, reused by every component rule in theme.css
            // instead of each selector picking its own ad-hoc value (previously 6/8/10/
            // 12/22px for radius and ~6 hand-written box-shadows across admin.css).
            borderRadius: {
                sm: '6px',  // badges, inputs
                md: '8px',  // buttons
                lg: '12px', // sections, cards
                xl: '16px', // modals, auth card
            },
            boxShadow: {
                sm: '0 1px 2px 0 rgba(15, 23, 42, 0.06)',
                DEFAULT: '0 1px 3px 0 rgba(15, 23, 42, 0.08), 0 1px 2px -1px rgba(15, 23, 42, 0.06)',
                md: '0 4px 12px -2px rgba(15, 23, 42, 0.1), 0 2px 4px -2px rgba(15, 23, 42, 0.06)',
                lg: '0 12px 32px -8px rgba(15, 23, 42, 0.16), 0 4px 8px -4px rgba(15, 23, 42, 0.08)',
            },
        },
    },
}
