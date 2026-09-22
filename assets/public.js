import '@hotwired/turbo';
import { Application } from '@hotwired/stimulus';
import NavController from './controllers/nav_controller.js';

const publicFrontend = Application.start();
publicFrontend.register('nav', NavController);
