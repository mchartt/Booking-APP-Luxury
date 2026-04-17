package com.smartdesk.backend.observer;

import com.smartdesk.backend.model.Desk;
import com.smartdesk.backend.state.AvailableState;
import com.smartdesk.backend.state.BookedState;

public class DeskAvailabilityManager extends Manager {

    private final Desk mainState;

    public DeskAvailabilityManager(Desk mainState) {
        this.mainState = mainState;
    }

    public void acquireDesk() {
        new AvailableState().handleBooking(mainState);
    }

    public void releaseDesk() {
        new BookedState().handleRelease(mainState);
        notifySubscribers(mainState);
    }

    public void mainBusinessLogic() {
        mainState.handleState();
    }
}
