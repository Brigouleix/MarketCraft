import React, { useState, useContext } from 'react';
import { Link } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { MapPin, CheckCircle, ShoppingBag, ArrowLeft, CreditCard, Lock } from 'lucide-react';
import { CartContext } from '../contexts/CartContext';
import { ordersAPI } from '../services/api';
import toast from 'react-hot-toast';

const PLACEHOLDER = 'https://images.unsplash.com/photo-1493106641515-6b5631de4bb9?w=100&q=60';
const SHIPPING_THRESHOLD = 60;
const SHIPPING_COST = 4.9;

const INITIAL_FORM = {
  prenom: '',
  nom: '',
  email: '',
  telephone: '',
  adresse: '',
  complement: '',
  ville: '',
  code_postal: '',
  pays: 'France',
};

// Paiement simulé : carte de test pré-remplie (aucun débit réel)
const INITIAL_PAYMENT = {
  methode: 'carte',
  numero: '4242 4242 4242 4242',
  expiration: '12/29',
  cvc: '123',
};

const PAYMENT_METHODS = [
  { value: 'carte', label: 'Carte bancaire' },
  { value: 'paypal', label: 'PayPal' },
  { value: 'virement', label: 'Virement' },
];

function InputField({ label, id, error, required, ...props }) {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-gray-700 mb-1.5">
        {label} {required && <span className="text-red-500">*</span>}
      </label>
      <input id={id} {...props} className={`input-field ${error ? 'border-red-400 focus:ring-red-200 focus:border-red-500' : ''}`} />
      {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
  );
}

export default function CheckoutPage() {
  const { items, total, clearCart } = useContext(CartContext);
  const [form, setForm] = useState(INITIAL_FORM);
  const [payment, setPayment] = useState(INITIAL_PAYMENT);
  const [errors, setErrors] = useState({});
  const [confirmed, setConfirmed] = useState(false);
  const [orderId, setOrderId] = useState(null);
  const [transactionId, setTransactionId] = useState(null);
  const [processing, setProcessing] = useState(false);

  const shippingFree = total >= SHIPPING_THRESHOLD;
  const shipping = shippingFree ? 0 : SHIPPING_COST;
  const grandTotal = total + shipping;

  const validate = () => {
    const errs = {};
    if (!form.prenom.trim()) errs.prenom = 'Prénom requis.';
    if (!form.nom.trim()) errs.nom = 'Nom requis.';
    if (!form.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) errs.email = 'Email invalide.';
    if (!form.adresse.trim()) errs.adresse = 'Adresse requise.';
    if (!form.ville.trim()) errs.ville = 'Ville requise.';
    if (!/^\d{4,6}$/.test(form.code_postal.trim())) errs.code_postal = 'Code postal invalide.';
    if (!form.pays.trim()) errs.pays = 'Pays requis.';
    if (payment.methode === 'carte') {
      if (!/^\d{16}$/.test(payment.numero.replace(/\s/g, ''))) errs.numero = 'Numéro de carte invalide (16 chiffres).';
      if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(payment.expiration.trim())) errs.expiration = 'Format MM/AA.';
      if (!/^\d{3}$/.test(payment.cvc.trim())) errs.cvc = 'CVC : 3 chiffres.';
    }
    return errs;
  };

  const { mutate, isPending } = useMutation({
    mutationFn: () =>
      ordersAPI.create({
        lignes: items.map((i) => ({ produit_id: i.id, quantite: i.quantity })),
        adresse_livraison: {
          nom_complet: `${form.prenom} ${form.nom}`.trim(),
          ligne1: form.adresse,
          ligne2: form.complement || null,
          ville: form.ville,
          code_postal: form.code_postal,
          pays: form.pays,
        },
        frais_livraison: shipping,
        paiement: {
          methode: payment.methode,
          detail:
            payment.methode === 'carte'
              ? `carte •••• ${payment.numero.replace(/\D/g, '').slice(-4)}`
              : payment.methode,
        },
      }),
    onSuccess: (res) => {
      const order = res.data?.data;
      setOrderId(order?.id ?? null);
      setTransactionId(order?.paiement?.transaction_id ?? null);
      clearCart();
      setConfirmed(true);
    },
    onError: (err) => {
      toast.error(err.response?.data?.error || err.response?.data?.message || 'Erreur lors de la commande. Réessayez.');
    },
    onSettled: () => setProcessing(false),
  });

  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors((prev) => ({ ...prev, [name]: '' }));
  };

  const handlePaymentChange = (e) => {
    const { name, value } = e.target;
    setPayment((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors((prev) => ({ ...prev, [name]: '' }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const errs = validate();
    if (Object.keys(errs).length > 0) {
      setErrors(errs);
      return;
    }
    // Simulation du traitement bancaire avant l'appel API
    setProcessing(true);
    setTimeout(() => mutate(), 1200);
  };

  // Confirmation screen
  if (confirmed) {
    return (
      <div className="max-w-lg mx-auto px-4 py-24 text-center flex flex-col items-center gap-6">
        <div className="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center">
          <CheckCircle size={48} className="text-green-500" />
        </div>
        <div>
          <h1 className="text-3xl font-serif font-bold text-gray-800 mb-3">
            Commande confirmée !
          </h1>
          <p className="text-gray-600 leading-relaxed">
            Merci pour votre commande. Votre numéro de commande est{' '}
            <strong className="text-primary">#{orderId}</strong>.
            {transactionId && (
              <>
                <br />
                Paiement accepté — transaction <strong className="font-mono text-sm">{transactionId}</strong>.
              </>
            )}
            <br />
            Un email de confirmation a été envoyé à <strong>{form.email}</strong>.
          </p>
        </div>
        <div className="flex gap-4">
          <Link to="/profil" className="btn-secondary flex items-center gap-2">
            Mes commandes
          </Link>
          <Link to="/produits" className="btn-primary flex items-center gap-2">
            <ShoppingBag size={16} /> Continuer
          </Link>
        </div>
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="max-w-lg mx-auto px-4 py-24 text-center flex flex-col items-center gap-6">
        <ShoppingBag size={56} className="text-secondary-400" />
        <h2 className="text-2xl font-serif font-bold text-gray-800">Votre panier est vide</h2>
        <Link to="/produits" className="btn-primary">Explorer les produits</Link>
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      {/* Header */}
      <Link to="/panier" className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary transition-colors mb-8">
        <ArrowLeft size={15} /> Retour au panier
      </Link>

      <h1 className="section-title mb-8">Finaliser la commande</h1>

      <div className="flex flex-col lg:flex-row gap-10">
        {/* Form */}
        <form onSubmit={handleSubmit} className="flex-1 space-y-6">
          {/* Delivery address */}
          <div className="card p-6">
            <h2 className="flex items-center gap-2 font-serif font-bold text-lg text-gray-800 mb-5">
              <MapPin size={18} className="text-primary" /> Adresse de livraison
            </h2>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <InputField
                label="Prénom" id="prenom" name="prenom" type="text"
                value={form.prenom} onChange={handleChange} error={errors.prenom}
                placeholder="Marie" required autoComplete="given-name"
              />
              <InputField
                label="Nom" id="nom" name="nom" type="text"
                value={form.nom} onChange={handleChange} error={errors.nom}
                placeholder="Dupont" required autoComplete="family-name"
              />
              <InputField
                label="Email" id="email" name="email" type="email"
                value={form.email} onChange={handleChange} error={errors.email}
                placeholder="marie@exemple.fr" required autoComplete="email"
                className="sm:col-span-2"
              />
              <div className="sm:col-span-2">
                <InputField
                  label="Téléphone" id="telephone" name="telephone" type="tel"
                  value={form.telephone} onChange={handleChange}
                  placeholder="+33 6 12 34 56 78" autoComplete="tel"
                />
              </div>
              <div className="sm:col-span-2">
                <InputField
                  label="Adresse" id="adresse" name="adresse" type="text"
                  value={form.adresse} onChange={handleChange} error={errors.adresse}
                  placeholder="12 rue des Artisans" required autoComplete="street-address"
                />
              </div>
              <div className="sm:col-span-2">
                <InputField
                  label="Complément d'adresse" id="complement" name="complement" type="text"
                  value={form.complement} onChange={handleChange}
                  placeholder="Apt. 4B, Bâtiment C…" autoComplete="address-line2"
                />
              </div>
              <InputField
                label="Code postal" id="code_postal" name="code_postal" type="text"
                value={form.code_postal} onChange={handleChange} error={errors.code_postal}
                placeholder="75001" required autoComplete="postal-code"
              />
              <InputField
                label="Ville" id="ville" name="ville" type="text"
                value={form.ville} onChange={handleChange} error={errors.ville}
                placeholder="Paris" required autoComplete="address-level2"
              />
              <div className="sm:col-span-2">
                <label htmlFor="pays" className="block text-sm font-medium text-gray-700 mb-1.5">
                  Pays <span className="text-red-500">*</span>
                </label>
                <select
                  id="pays" name="pays" value={form.pays} onChange={handleChange}
                  className="input-field cursor-pointer"
                >
                  {['France', 'Belgique', 'Suisse', 'Luxembourg', 'Canada', 'Autre'].map((p) => (
                    <option key={p} value={p}>{p}</option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* Paiement (simulé) */}
          <div className="card p-6">
            <h2 className="flex items-center gap-2 font-serif font-bold text-lg text-gray-800 mb-2">
              <CreditCard size={18} className="text-primary" /> Paiement
            </h2>
            <p className="flex items-center gap-1.5 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-5">
              <Lock size={12} /> Paiement simulé pour la démonstration — aucun débit réel. Une carte de test est pré-remplie.
            </p>

            {/* Méthode */}
            <div className="flex gap-3 mb-5">
              {PAYMENT_METHODS.map(({ value, label }) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => setPayment((p) => ({ ...p, methode: value }))}
                  className={`flex-1 px-3 py-2.5 rounded-xl border-2 text-sm font-medium transition-colors ${
                    payment.methode === value
                      ? 'border-primary bg-primary-50 text-primary'
                      : 'border-secondary-300 text-gray-600 hover:border-secondary-400'
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>

            {payment.methode === 'carte' ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="sm:col-span-2">
                  <InputField
                    label="Numéro de carte" id="numero" name="numero" type="text"
                    value={payment.numero} onChange={handlePaymentChange} error={errors.numero}
                    placeholder="4242 4242 4242 4242" required autoComplete="off" inputMode="numeric"
                  />
                </div>
                <InputField
                  label="Expiration (MM/AA)" id="expiration" name="expiration" type="text"
                  value={payment.expiration} onChange={handlePaymentChange} error={errors.expiration}
                  placeholder="12/29" required autoComplete="off"
                />
                <InputField
                  label="CVC" id="cvc" name="cvc" type="text"
                  value={payment.cvc} onChange={handlePaymentChange} error={errors.cvc}
                  placeholder="123" required autoComplete="off" inputMode="numeric"
                />
              </div>
            ) : (
              <p className="text-sm text-gray-500">
                Vous serez « redirigé » vers {payment.methode === 'paypal' ? 'PayPal' : 'votre banque'} — étape simulée dans cette version.
              </p>
            )}
          </div>

          <button
            type="submit"
            disabled={isPending || processing}
            className="btn-primary w-full flex items-center justify-center gap-2 py-3.5 text-base"
          >
            {isPending || processing ? (
              <>
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                {processing && !isPending ? 'Paiement en cours…' : 'Traitement en cours…'}
              </>
            ) : (
              <>
                <Lock size={16} /> Payer {grandTotal.toFixed(2)} €
              </>
            )}
          </button>
          <p className="text-center text-xs text-gray-400">
            En confirmant, vous acceptez nos conditions générales de vente.
          </p>
        </form>

        {/* Order summary */}
        <div className="lg:w-80 flex-shrink-0">
          <div className="card p-5 sticky top-20 space-y-4">
            <h3 className="font-serif font-bold text-base text-gray-800">
              Résumé ({items.length} article{items.length > 1 ? 's' : ''})
            </h3>

            {/* Items */}
            <div className="space-y-3 max-h-56 overflow-y-auto pr-1">
              {items.map((item) => (
                <div key={item.id} className="flex gap-3">
                  <img
                    src={item.image || item.images?.[0] || PLACEHOLDER}
                    alt={item.nom}
                    className="w-14 h-14 object-cover rounded-lg flex-shrink-0 border border-secondary-200"
                    onError={(e) => { e.currentTarget.src = PLACEHOLDER; }}
                  />
                  <div className="flex-1 min-w-0">
                    <p className="text-xs font-medium text-gray-800 line-clamp-2">{item.nom}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Qté : {item.quantity}</p>
                  </div>
                  <span className="text-sm font-semibold text-primary whitespace-nowrap">
                    {(item.prix * item.quantity).toFixed(2)} €
                  </span>
                </div>
              ))}
            </div>

            <div className="border-t border-secondary-200 pt-3 space-y-2 text-sm">
              <div className="flex justify-between text-gray-600">
                <span>Sous-total</span>
                <span>{total.toFixed(2)} €</span>
              </div>
              <div className="flex justify-between text-gray-600">
                <span>Livraison</span>
                <span className={shippingFree ? 'text-green-600 font-medium' : ''}>
                  {shippingFree ? 'Gratuite' : `${SHIPPING_COST.toFixed(2)} €`}
                </span>
              </div>
              <div className="flex justify-between font-bold text-base pt-2 border-t border-secondary-200">
                <span>Total TTC</span>
                <span className="text-primary">{grandTotal.toFixed(2)} €</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
