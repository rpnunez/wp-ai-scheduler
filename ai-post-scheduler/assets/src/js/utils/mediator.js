import Backbone from 'backbone';

/**
 * Shared Mediator Event Bus
 *
 * Allows disconnected views to communicate by triggering and listening
 * to global events.
 */
const mediator = Object.assign({}, Backbone.Events);

export default mediator;
