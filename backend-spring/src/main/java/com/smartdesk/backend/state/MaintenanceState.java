package com.smartdesk.backend.state;

import com.smartdesk.backend.model.Desk;

public class MaintenanceState implements DeskState {

    @Override
    public void handleState(Desk context) {
        context.setStateName("MAINTENANCE");
    }

    public void handleMaintence(Desk context) {
        context.setStateName("MAINTENANCE");
    }
}
