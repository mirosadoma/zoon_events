type EventSetupProps = {
  event: {
    id: string
    name: { en: string; ar: string }
    status: string
    tier: string
    readiness: string[]
  }
  can: {
    manage: boolean
    publish: boolean
  }
}

export default function EventSetup({ event, can }: EventSetupProps) {
  return (
    <main>
      <h1>{event.name.en}</h1>
      <p>{event.name.ar}</p>
      <dl>
        <dt>Status</dt><dd>{event.status}</dd>
        <dt>Tier</dt><dd>{event.tier}</dd>
      </dl>
      {event.readiness.length > 0 && (
        <section aria-labelledby="readiness-heading">
          <h2 id="readiness-heading">Publication readiness</h2>
          <ul>{event.readiness.map((item) => <li key={item}>{item}</li>)}</ul>
        </section>
      )}
      {can.manage && <button type="button">Save changes</button>}
      {can.publish && <button type="button" disabled={event.readiness.length > 0}>Publish</button>}
    </main>
  )
}
