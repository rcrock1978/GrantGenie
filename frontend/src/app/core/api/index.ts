/**
 * T036 wrapper: typed API client root.
 *
 * The actual generated modules/classes are produced by running
 * `ng-openapi-gen` against specs/001-grantgenie-core/contracts/api-openapi.yaml
 * (per frontend/openapi.json). This file is re-exported by feature modules
 * that need to call the backend.
 *
 *   import { OrgProfileControllerService, GrantControllerService } from '@app/core/api';
 *
 * To regenerate the client:
 *   cd frontend && npx ng-openapi-gen -c openapi.json
 */
export { ApiModule } from './api.module';
export { Configuration, ConfigurationParameters } from './configuration';
export { OrgProfileControllerService } from './api/orgProfileController.service';
export { GrantControllerService } from './api/grantController.service';
export { ProposalControllerService } from './api/proposalController.service';
export { BoilerplateControllerService } from './api/boilerplateController.service';
export { ReviewControllerService } from './api/reviewController.service';
export { TrackingControllerService } from './api/trackingController.service';
export { AdminControllerService } from './api/adminController.service';
export { AuthControllerService } from './api/authController.service';
export * from './model/models';
