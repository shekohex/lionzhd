import { ImgHTMLAttributes } from 'react';
import logoUrl from '../../images/logo.png';

export default function AppLogoIcon(props: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src={logoUrl} alt="App Logo" {...props} />;
}
