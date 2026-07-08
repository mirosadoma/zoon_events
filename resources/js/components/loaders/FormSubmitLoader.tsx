export default function FormSubmitLoader() {
  return (
    <div className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300" role="status">
      <span className="size-4 animate-spin rounded-full border-2 border-slate-300 border-t-sky-600" />
      Saving…
    </div>
  )
}
