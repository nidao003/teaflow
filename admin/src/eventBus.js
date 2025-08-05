// src/eventBus.js
import { reactive } from 'vue';

const eventBus = reactive({});

// Emit an event with data
const emit = (event, data) => {
  if (eventBus[event]) {
    eventBus[event].forEach(callback => callback(data)); // Call all registered callbacks with the data
  }
};

// Register a listener for an event
const on = (event, callback) => {
  if (!eventBus[event]) {
    eventBus[event] = []; // Initialize an array for the event if it doesn't exist
  }
  eventBus[event].push(callback); // Store the callback in the array
};

// Clear the listener for a specific event
const off = (event) => {
  delete eventBus[event]; // Remove the listener for this event
};

export { emit, on, off };