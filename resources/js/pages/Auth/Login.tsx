import { Head, useForm } from '@inertiajs/react'

export default function Login() {
  const form = useForm({ email: '', password: '', remember: false })

  return (
    <main className="grid min-h-screen place-items-center bg-slate-100 p-6 dark:bg-slate-950">
      <Head title="Sign in" />
      <form className="w-full max-w-md space-y-5 rounded-2xl bg-white p-8 shadow-xl dark:bg-slate-900" onSubmit={(event) => { event.preventDefault(); form.post('/login', { onFinish: () => form.reset('password') }) }}>
        <h1 className="text-2xl font-semibold">Zonetec foundation sign in</h1>
        <label className="grid gap-2">Email<input className="control" type="email" autoComplete="username" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></label>
        <label className="grid gap-2">Password<input className="control" type="password" autoComplete="current-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} /></label>
        <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} />Remember me</label>
        {Object.keys(form.errors).length > 0 && <p role="alert" className="text-red-700">Sign in failed. Check your credentials and try again.</p>}
        <button className="button-primary w-full" disabled={form.processing}>Sign in</button>
      </form>
    </main>
  )
}
