import FoundationLayout from '@/layouts/FoundationLayout'

const cards = [
  { label: 'Tenants', value: 'Isolation first' },
  { label: 'RBAC', value: 'Least privilege' },
  { label: 'Audit', value: 'Tamper evident' },
  { label: 'Health', value: 'Native operations' },
]

export default function FoundationDashboard() {
  return (
    <FoundationLayout>
    <div className="min-h-[70vh] bg-[radial-gradient(circle_at_top,_#dbeafe,_#f8fafc_60%)] px-6 py-12 text-slate-900 dark:bg-[radial-gradient(circle_at_top,_#172554,_#020617_60%)] dark:text-slate-50">
      <div className="mx-auto max-w-6xl space-y-10">
        <section className="rounded-3xl border border-slate-200/70 bg-white/80 p-8 shadow-[0_20px_80px_-35px_rgba(15,23,42,0.45)] backdrop-blur">
          <p className="text-sm font-semibold uppercase tracking-[0.3em] text-sky-700">
            Zonetec Phase 0
          </p>
          <h1 className="mt-4 max-w-3xl text-4xl font-semibold tracking-tight text-slate-950">
            Project foundation and governance, shaped for a secure multi-tenant future.
          </h1>
          <p className="mt-4 max-w-2xl text-base leading-7 text-slate-600">
            This shell is intentionally limited to foundation capabilities. Product features stay
            out until the tenant, access, audit, and operational layers are solid.
          </p>
        </section>

        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {cards.map((card) => (
            <article
              key={card.label}
              className="rounded-2xl border border-slate-200 bg-white/85 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
            >
              <p className="text-sm uppercase tracking-[0.22em] text-slate-500">{card.label}</p>
              <p className="mt-3 text-xl font-semibold text-slate-950">{card.value}</p>
            </article>
          ))}
        </section>
      </div>
    </div>
    </FoundationLayout>
  )
}
