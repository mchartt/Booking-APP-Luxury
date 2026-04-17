package com.smartdesk.backend.state;

import com.smartdesk.backend.model.Desk;

public class BookedState implements DeskState {

    @Override
    public void handleState(Desk context) {
        context.setStateName("BOOKED");
    }

    public void handleRelease(Desk context) {
        context.changeState(new AvailableState());
        context.setStateName("AVAILABLE");
    }
}
