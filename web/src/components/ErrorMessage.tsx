import React from 'react';
import { AlertCircle } from 'lucide-react';

interface ErrorMessageProps {
  message: string;
  onRetry?: () => void;
}

export const ErrorMessage: React.FC<ErrorMessageProps> = ({ message, onRetry }) => {
  return (
    <div className="flex flex-col items-center justify-center gap-4 py-12 px-4">
      <div className="w-16 h-16 rounded-full bg-cyber-pink/10 flex items-center justify-center">
        <AlertCircle className="w-8 h-8 text-cyber-pink" />
      </div>
      <p className="text-cyber-textMuted text-center max-w-md">{message}</p>
      {onRetry && (
        <button onClick={onRetry} className="btn-primary">
          حاول مرة أخرى
        </button>
      )}
    </div>
  );
};
