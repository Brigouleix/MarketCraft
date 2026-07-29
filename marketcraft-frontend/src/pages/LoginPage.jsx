import React, { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { Eye, EyeOff, Mail, Lock, Hammer, ShieldCheck, RefreshCw } from 'lucide-react';
import { useAuth } from '../hooks/useAuth';
import { authAPI } from '../services/api';

export default function LoginPage() {
  const { login, loading } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = location.state?.from?.pathname || '/';

  const [form, setForm] = useState({ email: '', password: '' });
  const [errors, setErrors] = useState({});
  const [showPassword, setShowPassword] = useState(false);
  // Défi anti-robot, activé par le serveur au-delà de 3 échecs de connexion.
  const [captcha, setCaptcha] = useState({ required: false, question: '', token: '', answer: '' });

  const chargerCaptcha = async () => {
    try {
      const { data } = await authAPI.captcha();
      const defi = data.data ?? data;
      setCaptcha({ required: true, question: defi.question, token: defi.token, answer: '' });
    } catch {
      // Si le défi ne se charge pas, le message d'erreur de connexion reste affiché.
    }
  };

  const validate = () => {
    const errs = {};
    if (!form.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) errs.email = 'Email invalide.';
    if (form.password.length < 6) errs.password = 'Mot de passe trop court (min. 6 caractères).';
    return errs;
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors((prev) => ({ ...prev, [name]: '' }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const errs = validate();
    if (captcha.required && !captcha.answer.trim())
      errs.captcha = 'Veuillez résoudre le calcul de sécurité.';
    if (Object.keys(errs).length > 0) { setErrors(errs); return; }

    const captchaPayload = captcha.required
      ? { captcha_token: captcha.token, captcha_reponse: captcha.answer.trim() }
      : undefined;

    const result = await login(form.email, form.password, captchaPayload);
    if (result.success) { navigate(from, { replace: true }); return; }

    // Le serveur exige un captcha (nouveau) ou en exigeait déjà : on (r)affiche
    // un défi frais pour la tentative suivante.
    if (result.details?.captcha_requis || captcha.required) {
      await chargerCaptcha();
    }
  };

  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4 py-12 bg-secondary-50">
      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="text-center mb-8">
          <Link to="/" className="inline-flex flex-col items-center gap-2">
            <div className="w-12 h-12 bg-primary rounded-xl flex items-center justify-center shadow-craft">
              <Hammer size={22} className="text-white" />
            </div>
            <span className="font-serif text-2xl font-bold text-primary">MarketCraft</span>
          </Link>
          <h1 className="text-2xl font-serif font-bold text-gray-800 mt-5 mb-1">Connexion</h1>
          <p className="text-gray-500 text-sm">Accédez à votre espace artisan ou acheteur</p>
        </div>

        <div className="card p-8 shadow-craft-lg">
          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
            {/* Email */}
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1.5">
                Email <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <Mail size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  id="email"
                  name="email"
                  type="email"
                  value={form.email}
                  onChange={handleChange}
                  placeholder="votre@email.fr"
                  autoComplete="email"
                  className={`input-field pl-10 ${errors.email ? 'border-red-400 focus:ring-red-200' : ''}`}
                  required
                />
              </div>
              {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
            </div>

            {/* Password */}
            <div>
              <div className="flex justify-between items-center mb-1.5">
                <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                  Mot de passe <span className="text-red-500">*</span>
                </label>
                <button type="button" className="text-xs text-primary hover:underline">
                  Mot de passe oublié ?
                </button>
              </div>
              <div className="relative">
                <Lock size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  id="password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  value={form.password}
                  onChange={handleChange}
                  placeholder="••••••••"
                  autoComplete="current-password"
                  className={`input-field pl-10 pr-10 ${errors.password ? 'border-red-400 focus:ring-red-200' : ''}`}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  tabIndex={-1}
                >
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
              {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
            </div>

            {/* Captcha — affiché seulement quand le serveur l'exige */}
            {captcha.required && (
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                <label htmlFor="captcha" className="flex items-center gap-1.5 text-sm font-medium text-amber-800 mb-2">
                  <ShieldCheck size={15} /> Vérification de sécurité <span className="text-red-500">*</span>
                </label>
                <div className="flex items-center gap-2">
                  <span className="px-3 py-2 rounded-lg bg-white border border-amber-300 text-gray-800 font-semibold text-sm whitespace-nowrap">
                    {captcha.question}
                  </span>
                  <input
                    id="captcha"
                    name="captcha"
                    type="text"
                    inputMode="numeric"
                    value={captcha.answer}
                    onChange={(e) => {
                      const answer = e.target.value;
                      setCaptcha((c) => ({ ...c, answer }));
                      if (errors.captcha) setErrors((p) => ({ ...p, captcha: '' }));
                    }}
                    placeholder="Réponse"
                    className={`input-field flex-1 ${errors.captcha ? 'border-red-400 focus:ring-red-200' : ''}`}
                  />
                  <button
                    type="button"
                    onClick={chargerCaptcha}
                    className="p-2 text-amber-700 hover:text-amber-900"
                    title="Générer un nouveau calcul"
                    aria-label="Générer un nouveau calcul"
                  >
                    <RefreshCw size={16} />
                  </button>
                </div>
                <p className="text-xs text-amber-700 mt-1.5">
                  Trop de tentatives : merci de confirmer que vous n'êtes pas un robot.
                </p>
                {errors.captcha && <p className="text-red-500 text-xs mt-1">{errors.captcha}</p>}
              </div>
            )}

            {/* Remember me */}
            <label className="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" className="rounded text-primary" />
              <span className="text-sm text-gray-600">Se souvenir de moi</span>
            </label>

            <button
              type="submit"
              disabled={loading}
              className="btn-primary w-full py-3 flex items-center justify-center gap-2 text-base"
            >
              {loading ? (
                <>
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  Connexion…
                </>
              ) : (
                'Se connecter'
              )}
            </button>
          </form>

          <div className="mt-6 text-center">
            <p className="text-sm text-gray-600">
              Pas encore de compte ?{' '}
              <Link to="/register" className="text-primary font-medium hover:underline">
                Créer un compte
              </Link>
            </p>
          </div>

          {/* Demo notice */}
          <div className="mt-6 p-3 bg-secondary-100 rounded-lg border border-secondary-300">
            <p className="text-xs text-gray-500 text-center">
              <strong>Démo :</strong> utilisez n'importe quel email/mot de passe valide.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
