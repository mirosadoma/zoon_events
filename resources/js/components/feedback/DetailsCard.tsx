type DetailsCardProps = {
  title: string
  items: Array<{ label: string; value: React.ReactNode }>
}

export default function DetailsCard({ title, items }: DetailsCardProps) {
  return (
    <section className="state-panel">
      <h2 className="text-lg font-semibold">{title}</h2>
      <dl className="mt-4 grid gap-3 sm:grid-cols-2">
        {items.map((item) => (
          <div key={item.label}>
            <dt className="text-xs uppercase tracking-wide text-slate-500">{item.label}</dt>
            <dd className="mt-1 font-medium">{item.value}</dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
