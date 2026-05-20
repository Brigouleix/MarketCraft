import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { Hammer, Mail, Instagram, Facebook, Twitter, ArrowRight } from 'lucide-react';
import toast from 'react-hot-toast';

const footerLinks = {
  Boutique: [
    { label: 'Tous les produits', to: '/produits' },
    { label: 'Boutiques', to: '/boutiques' },
    { label: 'Nouveautés', to: '/produits?tri=recent' },
    { label: 'Meilleures ventes', to: '/produits?tri=populaire' },
  ],
  'Devenir vendeur': [
    { label: 'Créer un compte', to: '/register' },
    { label: 'Mon dashboard', to: '/dashboard' },
    { label: 'Guide vendeur', to: '#' },
    { label: 'Tarifs', to: '#' },
  ],
  Aide: [
    { label: 'FAQ', to: '#' },
    { label: 'Livraison & retours', to: '#' },
    { label: 'Nous contacter', to: '#' },
    { label: 'Mentions légales', to: '#' },
  ],
};

const socials = [
  { icon: Instagram, href: '#', label: 'Instagram' },
  { icon: Facebook, href: '#', label: 'Facebook' },
  { icon: Twitter, href: '#', label: 'Twitter' },
];

export default function Footer() {
  const [email, setEmail] = useState('');

  const handleNewsletter = (e) => {
    e.preventDefault();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      toast.error('Veuillez entrer un email valide.');
      return;
    }
    toast.success('Merci pour votre inscription à la newsletter !');
    setEmail('');
  };

  return (
    <footer className="bg-gray-900 text-gray-300">
      {/* Newsletter band */}
      <div className="bg-primary">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex flex-col md:flex-row items-center justify-between gap-6">
          <div>
            <h3 className="text-white text-xl font-serif font-bold">
              Rejoignez notre communauté
            </h3>
            <p className="text-primary-200 text-sm mt-1">
              Recevez nos offres exclusives et nouveautés artisanales.
            </p>
          </div>
          <form onSubmit={handleNewsletter} className="flex gap-2 w-full md:w-auto">
            <div className="relative flex-1 md:w-72">
              <Mail size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-primary-300" />
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="votre@email.fr"
                className="w-full pl-9 pr-4 py-2.5 rounded-lg bg-white/10 text-white placeholder-primary-200
                           border border-white/20 focus:outline-none focus:ring-2 focus:ring-white/40 text-sm"
              />
            </div>
            <button
              type="submit"
              className="flex items-center gap-1.5 bg-white text-primary font-semibold px-4 py-2.5 rounded-lg
                         hover:bg-secondary-100 transition-colors text-sm whitespace-nowrap"
            >
              S'inscrire <ArrowRight size={14} />
            </button>
          </form>
        </div>
      </div>

      {/* Main footer */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8">
          {/* Brand */}
          <div className="lg:col-span-2">
            <Link to="/" className="flex items-center gap-2 mb-4 group">
              <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                <Hammer size={16} className="text-white" />
              </div>
              <span className="font-serif text-xl font-bold text-white">MarketCraft</span>
            </Link>
            <p className="text-sm text-gray-400 leading-relaxed max-w-xs">
              La marketplace dédiée aux artisans du monde. Découvrez des créations
              uniques, faites à la main avec passion et savoir-faire.
            </p>
            <div className="flex gap-3 mt-5">
              {socials.map(({ icon: Icon, href, label }) => (
                <a
                  key={label}
                  href={href}
                  aria-label={label}
                  className="w-9 h-9 bg-gray-800 rounded-lg flex items-center justify-center
                             hover:bg-primary transition-colors duration-200"
                >
                  <Icon size={16} />
                </a>
              ))}
            </div>
          </div>

          {/* Links */}
          {Object.entries(footerLinks).map(([title, links]) => (
            <div key={title}>
              <h4 className="text-white font-semibold text-sm mb-4">{title}</h4>
              <ul className="space-y-2.5">
                {links.map(({ label, to }) => (
                  <li key={label}>
                    <Link
                      to={to}
                      className="text-sm text-gray-400 hover:text-white transition-colors duration-150"
                    >
                      {label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        {/* Bottom bar */}
        <div className="mt-10 pt-6 border-t border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4">
          <p className="text-xs text-gray-500">
            © {new Date().getFullYear()} MarketCraft. Tous droits réservés.
          </p>
          <div className="flex items-center gap-4 text-xs text-gray-500">
            <a href="#" className="hover:text-white transition-colors">Politique de confidentialité</a>
            <a href="#" className="hover:text-white transition-colors">CGV</a>
            <a href="#" className="hover:text-white transition-colors">Cookies</a>
          </div>
        </div>
      </div>
    </footer>
  );
}
