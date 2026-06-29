import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { Eye, EyeOff, Mail, Lock, Hammer, RefreshCw, ShieldAlert, Clock } from 'lucide-react';
import { useAuth } from '../hooks/useAuth';
import { authAPI } from '../services/api';

// ─── Constants ────────────────────────────────────────────────────────────────

const LS_ATTEMPTS   = 'mc_login_attempts';   // { count, resetAt }
const LS_BLOCKED    = 'mc_login_blocked';     // timestamp (ms) until when we are blocked
const MAX_LOCAL_ATTEMPTS = 5;

// ─── Captcha widget ───────────────────────────────────────────────────────────

function CaptchaWidget({ value, onChange, error, onRefresh, question, loading }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">
        Question de sécurité <span className="text-red-500">*</span>
      </label>
      <div className="flex items-center gap-2 mb-2">
        <span className="flex-1 bg-secondary-100 border border-secondary-300 rounded-lg px-4 py-2.5 text-sm font-mono font-semibold text-gray-800 select-none">
          {loading ? '…' : (question || 'Chargement…')}
        </span>
        <button
          type="button"
          onClick={onRefresh}
          disabled={loading}
          title="Nouvelle question"
          className="p-2.5 text-gray-400 hover:text-primary hover:bg-secondary-100 rounded-lg transition-colors"
        >
          <RefreshCw size={15} className={loading ? 'animate-spin' : ''} />
        </button>
      </div>
      <input
        type="number"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder="Votre réponse"
        className={`input-field ${error ? 'border-red-400 focus:ring-red-200' : ''}`}
        autoComplete="off"
      />
      {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
  );
}

// ─── Lockout banner ───────────────────────────────────────────────────────────

function LockoutBanner({ seconds }) {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  const label = mins > 0
    ? `${mins} min ${secs.toString().padStart(2, '0')} s`
    : `${secs} s`;

  return (
    <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl p-4 mb-2">
      <ShieldAlert size={20} className="text-red-500 flex-shrink-0 mt-0.5" />
      <div>
        <p className="text-sm font-semibold text-red-700">Compte temporairement bloqué</p>
        <p className="text-xs text-red-600 mt-0.5">
          Trop de tentatives échouées. Réessayez dans&nbsp;
          <span className="font-mono font-bold inline-flex items-center gap-1">
            <Clock size={11} /> {label}
          </span>
        </p>
      </div>
    </div>
  );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function LoginPage() {
  const { login, loading: authLoading } = useAuth();
  const navigate  = useNavigate();
  const location  = useLocation();
  const from      = location.state?.from?.pathname || '/';

  // Form state
  const [form, setForm]               = useState({ email: '', password: '' });
  const [errors, setErrors]           = useState({});
  const [showPassword, setShowPassword] = useState(false);

  // Captcha state
  const [captcha, setCaptcha]         = useState(null);  // { question, token }
  const [captchaAnswer, setCaptchaAnswer] = useState('');
  const [captchaLoading, setCaptchaLoading] = useState(false);

  // Rate-limit state
  const [failedAttempts, setFailedAttempts] = useState(() => {
    try {
      const stored = JSON.parse(localStorage.getItem(LS_ATTEMPTS) || '{}');
      if (stored.resetAt && Date.now() > stored.resetAt) return 0;
      return stored.count || 0;
    } catch { return 0; }
  });
  const [blockedUntil, setBlockedUntil] = useState(() => {
    const ts = parseInt(localStorage.getItem(LS_BLOCKED) || '0', 10);
    return ts > Date.now() ? ts : null;
  });
  const [countdown, setCountdown]     = useState(0);
  const countdownRef                  = useRef(null);

  // ── Captcha fetch ──────────────────────────────────────────────────────────

  const fetchCaptcha = useCallback(async () => {
    setCaptchaLoading(true);
    try {
      const { data } = await authAPI.captcha();
      setCaptcha(data.data ?? data);
      setCaptchaAnswer('');
    } catch {
      // Fallback: static challenge so the form stays usable offline
      setCaptcha({ question: 'Combien font 2 + 3 ?', token: '' });
    } finally {
      setCaptchaLoading(false);
    }
  }, []);

  useEffect(() => { fetchCaptcha(); }, [fetchCaptcha]);

  // ── Countdown timer ────────────────────────────────────────────────────────

  useEffect(() => {
    if (!blockedUntil) {
      setCountdown(0);
      if (countdownRef.current) clearInterval(countdownRef.current);
      return;
    }

    const tick = () => {
      const remaining = Math.max(0, Math.ceil((blockedUntil - Date.now()) / 1000));
      setCountdown(remaining);
      if (remaining === 0) {
        clearInterval(countdownRef.current);
        setBlockedUntil(null);
        localStorage.removeItem(LS_BLOCKED);
        fetchCaptcha();
      }
    };

    tick();
    countdownRef.current = setInterval(tick, 1000);
    return () => clearInterval(countdownRef.current);
  }, [blockedUntil, fetchCaptcha]);

  // ── Validation ─────────────────────────────────────────────────────────────

  const validate = () => {
    const errs = {};
    if (!form.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) errs.email = 'Email invalide.';
    if (form.password.length < 6) errs.password = 'Mot de passe trop court (min. 6 caractères).';
    if (!captchaAnswer.trim()) errs.captcha = 'Veuillez répondre à la question de sécurité.';
    return errs;
  };

  // ── Submit ─────────────────────────────────────────────────────────────────

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (blockedUntil && Date.now() < blockedUntil) return;

    const errs = validate();
    if (Object.keys(errs).length > 0) { setErrors(errs); return; }
    setErrors({});

    const result = await login(form.email, form.password, {
      captcha_token:  captcha?.token  ?? '',
      captcha_answer: captchaAnswer,
    });

    if (result.success) {
      localStorage.removeItem(LS_ATTEMPTS);
      localStorage.removeItem(LS_BLOCKED);
      navigate(from, { replace: true });
      return;
    }

    // IP blocked by the server
    if (result.blocked) {
      const until = Date.now() + (result.retry_after * 1000);
      setBlockedUntil(until);
      localStorage.setItem(LS_BLOCKED, until.toString());
      fetchCaptcha();
      return;
    }

    // Regular failure — increment local counter
    const newCount = failedAttempts + 1;
    setFailedAttempts(newCount);
    localStorage.setItem(LS_ATTEMPTS, JSON.stringify({
      count:   newCount,
      resetAt: Date.now() + 15 * 60 * 1000,
    }));

    // Client-side soft block after MAX_LOCAL_ATTEMPTS (mirrors server logic)
    if (newCount >= MAX_LOCAL_ATTEMPTS) {
      const until = Date.now() + 15 * 60 * 1000;
      setBlockedUntil(until);
      localStorage.setItem(LS_BLOCKED, until.toString());
    }

    fetchCaptcha();
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((p) => ({ ...p, [name]: value }));
    if (errors[name]) setErrors((p) => ({ ...p, [name]: '' }));
  };

  const isBlocked = Boolean(blockedUntil && Date.now() < blockedUntil);
  const loading   = authLoading;

  // ── Render ─────────────────────────────────────────────────────────────────

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

          {/* Lockout banner */}
          {isBlocked && <LockoutBanner seconds={countdown} />}

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
                  disabled={isBlocked}
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
                <a href="#" className="text-xs text-primary hover:underline">
                  Mot de passe oublié ?
                </a>
              </div>
              <div className="relative">
                <Lock size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  id="password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  value={form.password}
                  onChange={handleChange}
                  disabled={isBlocked}
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

            {/* Captcha */}
            <CaptchaWidget
              question={captcha?.question}
              value={captchaAnswer}
              onChange={setCaptchaAnswer}
              error={errors.captcha}
              onRefresh={fetchCaptcha}
              loading={captchaLoading}
            />

            {/* Failed attempts warning */}
            {failedAttempts > 0 && failedAttempts < MAX_LOCAL_ATTEMPTS && !isBlocked && (
              <p className="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                ⚠️ {MAX_LOCAL_ATTEMPTS - failedAttempts} tentative(s) restante(s) avant blocage temporaire.
              </p>
            )}

            {/* Remember me */}
            <label className="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" className="rounded text-primary" />
              <span className="text-sm text-gray-600">Se souvenir de moi</span>
            </label>

            <button
              type="submit"
              disabled={loading || isBlocked}
              className="btn-primary w-full py-3 flex items-center justify-center gap-2 text-base disabled:opacity-60 disabled:cursor-not-allowed"
            >
              {loading ? (
                <>
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  Connexion…
                </>
              ) : isBlocked ? (
                <>
                  <Clock size={16} />
                  Bloqué — {Math.ceil(countdown / 60)} min restante(s)
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

        </div>
      </div>
    </div>
  );
}
