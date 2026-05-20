import React from 'react';
import { Star } from 'lucide-react';

/**
 * StarRating – read-only or interactive.
 *
 * Props:
 *  value      – current rating (number 0-5)
 *  onChange   – if provided, makes component interactive
 *  max        – total stars (default 5)
 *  size       – icon size in px (default 18)
 *  showValue  – show numeric value next to stars
 *  count      – review count to display
 */
export default function StarRating({
  value = 0,
  onChange,
  max = 5,
  size = 18,
  showValue = false,
  count,
  className = '',
}) {
  const [hovered, setHovered] = React.useState(null);
  const interactive = Boolean(onChange);
  const display = hovered !== null ? hovered : value;

  return (
    <div className={`flex items-center gap-1 ${className}`}>
      {Array.from({ length: max }, (_, i) => {
        const starValue = i + 1;
        const filled = starValue <= Math.round(display);

        return (
          <button
            key={i}
            type="button"
            disabled={!interactive}
            onClick={() => interactive && onChange(starValue)}
            onMouseEnter={() => interactive && setHovered(starValue)}
            onMouseLeave={() => interactive && setHovered(null)}
            className={`focus:outline-none transition-transform duration-100 ${
              interactive ? 'cursor-pointer hover:scale-110' : 'cursor-default'
            }`}
            aria-label={`${starValue} étoile${starValue > 1 ? 's' : ''}`}
          >
            <Star
              size={size}
              className={`transition-colors duration-100 ${
                filled
                  ? 'text-amber-400 fill-amber-400'
                  : 'text-gray-300 fill-gray-100'
              }`}
            />
          </button>
        );
      })}

      {showValue && (
        <span className="text-sm font-medium text-gray-700 ml-1">
          {Number(value).toFixed(1)}
        </span>
      )}

      {count !== undefined && (
        <span className="text-sm text-gray-500 ml-0.5">({count})</span>
      )}
    </div>
  );
}
