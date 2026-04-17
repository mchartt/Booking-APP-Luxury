package com.smartdesk.backend.state;

import com.smartdesk.backend.model.Desk;

public interface DeskState {

    void handleState(Desk context);

    static DeskState from(String name) {
        return switch (name) {
            case "BOOKED" -> new BookedState();
            case "MAINTENANCE" -> new MaintenanceState();
            default -> new AvailableState();
        };
    }
}
