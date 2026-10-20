# UI and frontend

- Brand colors: `#1D9E75` green (income, positive) · `#0F6E56` dark green · `#B45309` amber (expense, reduction) · `#B91C1C` red (loss) · `#7C3AED` purple (correction).
- Green always means income or positive. Corrections use the purple "Corrected" tag.
- Every admin CRUD list page shows skeleton placeholders on first load, full refetch and inline row actions.
- Every form field shows its own validation error directly beneath it.
- Admin approve, reject and save actions use AJAX and update the page in place, with no full reload.
- Use `useTheme()` inside `ThemeContext.Provider`: an outer `Index` wrapper and an inner `IndexContent` on every page.
- Write `Pick<>` generics on one line in `.tsx` files. The Vite oxc parser rejects multi-line generics.
- Every date shown is DD/MM/YYYY.
