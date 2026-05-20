import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Eye, EyeOff, Mail, Lock, User, Hammer, ShoppingBag, Store } from 'lucide-react';
import { useAuth } from '../hooks/useAuth';

export default function RegisterPage() {
  const { register, loading } = useAuth();
  const navigate = useNavigate();

  const [form, setForm] = useState({
    prenom: '',
    nom: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'acheteur',
  });
  const [errors, setErrors] = useState({});
  const [showPwd, setShowPwd] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);

  const validate = () => {
    const errs = {};
    if (!form.prenom.trim()) errs.prenom = 'Prénom requis.';
    if (!form.nom.trim()) errs.nom = 'Nom requis.';
    if (!form.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) errs.email = 'Email invalide.';
    if (form.password.length < 8) errs.password = 'Minimum 8 caractères.';
    if (!/[A-Z]/.test(form.password)) errs.password = 'Au moins une majuscule requise.';
    if (!/[0-9]/.test(form.password)) errs.password = 'Au moins un chiffre requis.';
    if (form.password !== form.password_confirmation)
      errs.password_confirmation = 'Les mots de passe ne correspondent pas.';
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
    if (Object.keys(errs).length > 0) { setErrors(errs); return; }
    const result = await register(form);
    if (result.success) {
      navigate(form.role === 'vendeur' ? '/dashboard' : '/');
    }
  };

  const passwordStrength = () => {
    const p = form.password;
    if (!p) return 0;
    let score = 0;
    if (p.length >= 8) score++;
    if (p.length >= 12) score++;
    if (/[A-Z]/.test(p)) score++;
    if (/[0-9]/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;
    return score;
  };

  const strengthLabels = ['', 'Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
  const strengthColors = ['', 'bg-red-500', 'bg-orange-400', 'bg-yellow-400', 'bg-green-400', 'bg-green-600'];
  const strength = passwordStrength();

  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4 py-12 bg-secondary-50">
      <div className="w-full max-w-lg">
        {/* Logo */}
        <div className="text-center mb-8">
          <Link to="/" className="inline-flex flex-col items-center gap-2">
            <div className="w-12 h-12 bg-primary rounded-xl flex items-center justify-center shadow-craft">
              <Hammer size={22} className="text-white" />
            </div>
            <span className="font-serif text-2xl font-bold text-primary">MarketCraft</span>
          </Link>
          <h1 className="text-2xl font-serif font-bold text-gray-800 mt-5 mb-1">Créer un compte</h1>
          <p className="text-gray-500 text-sm">Rejoignez la communauté MarketCraft</p>
        </div>

        <div className="card p-8 shadow-craft-lg">
          {/* Role selector */}
          <div className="grid grid-cols-2 gap-3 mb-6">
            {[
              { value: 'acheteur', label: 'Je suis acheteur', icon: ShoppingBag, desc: 'Je cherche des créations uniques' },
              { value: 'vendeur', label: 'Je suis artisan', icon: Store, desc: 'Je veux vendre mes créations' },
            ].map(({ value, label, icon: Icon, desc }) => (
              <button
                key={value}
                type="button"
                onClick={() => setForm((p) => ({ ...p, role: value }))}
                className={`p-4 rounded-xl border-2 text-left transition-all duration-200 ${
                  form.role === value
                    ? 'border-primary bg-primary-50'
                    : 'border-secondary-300 hover:border-secondary-400 bg-white'
                }`}
              >
                <Icon
                  size={20}
                  className={`mb-2 ${form.role === value ? 'text-primary' : 'text-gray-400'}`}
                />
                <p className={`font-semibold text-sm ${form.role === value ? 'text-primary' : 'text-gray-700'}`}>
                  {label}
                </p>
                <p className="text-xs text-gray-500 mt-0.5">{desc}</p>
              </button>
            ))}
          </div>

          <form onSubmit={handleSubmit} className="space-y-4" noValidate>
            {/* Name */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="prenom" className="block text-sm font-medium text-gray-700 mb-1.5">
                  Prénom <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <User size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input
                    id="prenom" name="prenom" type="text" value={form.prenom}
                    onChange={handleChange} placeholder="Marie" autoComplete="given-name"
                    className={`input-field pl-9 text-sm ${errors.prenom ? 'border-red-400' : ''}`}
                    required
                  />
                </div>
                {errors.prenom && <p className="text-red-500 text-xs mt-1">{errors.prenom}</p>}
              </div>
              <div>
                <label htmlFor="nom" className="block text-sm font-medium text-gray-700 mb-1.5">
                  Nom <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <User size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input
                    id="nom" name="nom" type="text" value={form.nom}
                    onChange={handleChange} placeholder="Dupont" autoComplete="family-name"
                    className={`input-field pl-9 text-sm ${errors.nom ? 'border-red-400' : ''}`}
                    required
                  />
                </div>
                {errors.nom && <p className="text-red-500 text-xs mt-1">{errors.nom}</p>}
              </div>
            </div>

            {/* Email */}
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1.5">
                Email <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <Mail size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  id="email" name="email" type="email" value={form.email}
                  onChange={handleChange} placeholder="marie@exemple.fr" autoComplete="email"
                  className={`input-field pl-9 text-sm ${errors.email ? 'border-red-400' : ''}`}
                  required
                />
              </div>
              {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
            </div>

            {/* Password */}
            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1.5">
                Mot de passe <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <Lock size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  id="password" name="password" type={showPwd ? 'text' : 'password'}
                  value={form.password} onChange={handleChange}
                  placeholder="Min. 8 caractères, 1 majuscule, 1 chiffre"
                  autoComplete="new-password"
                  className={`input-field pl-9 pr-10 text-sm ${errors.password ? 'border-red-400' : ''}`}
                  required
                />
                <button type="button" onClick={() => setShowPwd((v) => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" tabIndex={-1}>
                  {showPwd ? <EyeOff size={15} /> : <Eye size={15} />}
                </button>
              </div>
              {form.password && (
                <div className="mt-2">
                  <div className="flex gap-1 h-1 rounded-full overflow-hidden">
                    {Array.from({ length: 5 }, (_, i) => (
                      <div key={i} className={`flex-1 rounded-full transition-colors ${i < strength ? strengthColors[strength] : 'bg-secondary-300'}`} />
                    ))}
                  </div>
                  <p className="text-xs text-gray-500 mt-1">
                    Force : <span className="font-medium">{strengthLabels[strength]}</span>
                  </p>
                </div>
              )}
              {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
            </div>

            {/* Confirm password */}
            <div>
              <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700 mb-1.5">
                Confirmer le mot de passe <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <Lock size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  id="password_confirmation" name="password_confirmation"
                  type={showConfirm ? 'text' : 'password'}
                  value={form.password_confirmation} onChange={handleChange}
                  placeholder="Répétez le mot de passe" autoComplete="new-password"
                  className={`input-field pl-9 pr-10 text-sm ${errors.password_confirmation ? 'border-red-400' : ''}`}
                  required
                />
                <button type="button" onClick={() => setShowConfirm((v) => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" tabIndex={-1}>
                  {showConfirm ? <EyeOff size={15} /> : <Eye size={15} />}
                </button>
              </div>
              {errors.password_confirmation && (
                <p className="text-red-500 text-xs mt-1">{errors.password_confirmation}</p>
              )}
            </div>

            {/* Terms */}
            <label className="flex items-start gap-2 cursor-pointer select-none">
              <input type="checkbox" required className="mt-0.5 text-primary" />
              <span className="text-xs text-gray-600">
                En m'inscrivant, j'accepte les{' '}
                <a href="#" className="text-primary underline">conditions d'utilisation</a> et la{' '}
                <a href="#" className="text-primary underline">politique de confidentialité</a>.
              </span>
            </label>

            <button
              type="submit"
              disabled={loading}
              className="btn-primary w-full py-3 flex items-center justify-center gap-2 text-base"
            >
              {loading ? (
                <>
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  Création du compte…
                </>
              ) : (
                'Créer mon compte'
              )}
            </button>
          </form>

          <p className="mt-5 text-center text-sm text-gray-600">
            Déjà inscrit ?{' '}
            <Link to="/login" className="text-primary font-medium hover:underline">
              Se connecter
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
