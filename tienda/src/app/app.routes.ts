import { Routes } from '@angular/router';
import { HomeComponent } from './pages/home/home';
import { NotFoundComponent } from './pages/not-found/not-found';

export const routes: Routes = [
  { path: '', component: HomeComponent },
  { path: '**', component: NotFoundComponent },
];